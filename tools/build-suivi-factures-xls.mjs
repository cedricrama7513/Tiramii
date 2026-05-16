import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDirs = [
  path.join(__dirname, '..', 'outils'),
  path.join(__dirname, '..', 'a-telecharger'),
];

const maxRow = 120;
const alertThreshold = 500;

const clientsJsonPath = path.join(__dirname, 'pro-clients.json');

function loadRestaurants() {
  if (!fs.existsSync(clientsJsonPath)) {
    throw new Error(
      `Fichier ${clientsJsonPath} introuvable. Lancez d'abord : php tools/export-pro-clients-json.php (sur le serveur avec config.php).`
    );
  }
  const raw = JSON.parse(fs.readFileSync(clientsJsonPath, 'utf8'));
  const list = raw.clients;
  if (!Array.isArray(list) || list.length < 1) {
    throw new Error('pro-clients.json : tableau "clients" vide.');
  }
  return list.map((c) => ({
    name: String(c.name),
    sheetName: String(c.sheetName || c.name).slice(0, 31),
    invoicePrefix: String(c.invoicePrefix || 'FAC'),
    monthlyAmounts: (c.monthlyAmounts || []).map(Number),
    paidMonths: (c.paidMonths || []).map(Number),
  }));
}

const restaurants = loadRestaurants();

const headers = [
  'Date facture',
  'N° facture',
  'Montant TTC (€)',
  'Payé ? (Oui / Non)',
  'Alerte si facture > 500 €',
  'Encours impayé (€)',
  'Alerte encours > 500 €',
  'Message envoyé ? (Oui / Non)',
  'Date relance',
];

function colLetter(n) {
  let s = '';
  while (n > 0) {
    n--;
    s = String.fromCharCode(65 + (n % 26)) + s;
    n = Math.floor(n / 26);
  }
  return s;
}

function escapeXml(s) {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function cell(row, col, type, value, extra = '') {
  if (type === 'formula') {
    return `<Cell ss:Index="${col}" ss:Formula="${escapeXml(value)}"><Data ss:Type="String"></Data></Cell>`;
  }
  const dataType = type === 'number' ? 'Number' : 'String';
  return `<Cell ss:Index="${col}"${extra}><Data ss:Type="${dataType}">${escapeXml(String(value))}</Data></Cell>`;
}

function sheetRef(sheetName) {
  return `'${sheetName.replace(/'/g, "''")}'`;
}

function pad2(n) {
  return String(n).padStart(2, '0');
}

function buildInvoiceRows(restaurant) {
  const rows = [];
  for (let month = 1; month <= 12; month++) {
    const amount = restaurant.monthlyAmounts[month - 1] ?? '';
    const date = `2026-${pad2(month)}-01`;
    const invNum = `${restaurant.invoicePrefix}-2026-${pad2(month)}`;
    const paid = restaurant.paidMonths.includes(month) ? 'Oui' : 'Non';
    const messageSent = paid === 'Oui' ? 'Oui' : 'Non';
    const relanceDate = paid === 'Oui' ? `2026-${pad2(month)}-15` : '';
    rows.push({ date, invNum, amount, paid, messageSent, relanceDate });
  }
  return rows;
}

function buildWorksheetXml(restaurant) {
  const prefill = buildInvoiceRows(restaurant);
  let rowsXml = '';

  rowsXml += '<Row ss:StyleID="header">';
  headers.forEach((h, i) => {
    rowsXml += cell(1, i + 1, 'string', h, ' ss:StyleID="header"');
  });
  rowsXml += '</Row>\n';

  for (let r = 2; r <= maxRow; r++) {
    const data = prefill[r - 2];
    rowsXml += '<Row>';
    if (data) {
      rowsXml += cell(r, 1, 'string', data.date);
      rowsXml += cell(r, 2, 'string', data.invNum);
      rowsXml += cell(r, 3, 'number', data.amount, ' ss:StyleID="money"');
      rowsXml += cell(r, 4, 'string', data.paid);
    } else {
      rowsXml += cell(r, 1, 'string', '');
      rowsXml += cell(r, 2, 'string', '');
      rowsXml += cell(r, 3, 'number', '');
      rowsXml += cell(r, 4, 'string', 'Non');
    }

    rowsXml += cell(
      r,
      5,
      'formula',
      `=SI(C${r}&gt;${alertThreshold};&quot;⚠ Facture &gt; ${alertThreshold} € — à relancer&quot;;&quot;&quot;)`,
      ' ss:StyleID="alert"'
    );
    rowsXml += cell(
      r,
      6,
      'formula',
      `=SOMME($C$2:$C$${maxRow})-SOMME.SI($C$2:$C$${maxRow};$D$2:$D$${maxRow};&quot;Oui&quot;)`
    );
    rowsXml += cell(
      r,
      7,
      'formula',
      `=SI(F${r}&gt;${alertThreshold};&quot;⚠ ENVOYER MESSAGE PAIEMENT&quot;;&quot;&quot;)`,
      ' ss:StyleID="alert"'
    );

    if (data) {
      rowsXml += cell(r, 8, 'string', data.messageSent);
      rowsXml += cell(r, 9, 'string', data.relanceDate);
    } else {
      rowsXml += cell(r, 8, 'string', 'Non');
      rowsXml += cell(r, 9, 'string', '');
    }
    rowsXml += '</Row>\n';
  }

  return ` <Worksheet ss:Name="${escapeXml(restaurant.sheetName)}">
  <Table ss:ExpandedColumnCount="9" ss:ExpandedRowCount="${maxRow}" x:FullColumns="1" x:FullRows="1" ss:DefaultColumnWidth="120">
   <Column ss:Width="95"/>
   <Column ss:Width="130"/>
   <Column ss:Width="95"/>
   <Column ss:Width="85"/>
   <Column ss:Width="200"/>
   <Column ss:Width="120"/>
   <Column ss:Width="200"/>
   <Column ss:Width="110"/>
   <Column ss:Width="95"/>
${rowsXml}
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>1</SplitHorizontal>
   <TopRowBottomPane>1</TopRowBottomPane>
   <ActivePane>2</ActivePane>
  </WorksheetOptions>
 </Worksheet>`;
}

function buildSyntheseXml() {
  let synthRows = '';
  synthRows += '<Row ss:StyleID="header">';
  ['Restaurant', 'Encours impayé (€)', 'Alerte > 500 €'].forEach((h, i) => {
    synthRows += cell(1, i + 1, 'string', h, ' ss:StyleID="header"');
  });
  synthRows += '</Row>\n';

  restaurants.forEach((restaurant, idx) => {
    const r = idx + 2;
    const ref = sheetRef(restaurant.sheetName);
    synthRows += '<Row>';
    synthRows += cell(r, 1, 'string', restaurant.name);
    synthRows += cell(
      r,
      2,
      'formula',
      `=SOMME(${ref}!$C$2:$C$${maxRow})-SOMME.SI(${ref}!$C$2:$C$${maxRow};${ref}!$D$2:$D$${maxRow};&quot;Oui&quot;)`
    );
    synthRows += cell(
      r,
      3,
      'formula',
      `=SI(B${r}&gt;${alertThreshold};&quot;⚠ RELANCER&quot;;&quot;OK&quot;)`,
      ' ss:StyleID="alert"'
    );
    synthRows += '</Row>\n';
  });

  return ` <Worksheet ss:Name="Synthèse">
  <Table ss:ExpandedColumnCount="3" ss:ExpandedRowCount="10">
   <Column ss:Width="180"/>
   <Column ss:Width="120"/>
   <Column ss:Width="120"/>
${synthRows}
  </Table>
 </Worksheet>`;
}

function buildWorkbookXml() {
  const worksheets = restaurants.map((r) => buildWorksheetXml(r)).join('\n');

  return `<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Title>Suivi factures restaurants pro — Casa Dessert</Title>
  <Author>Casa Dessert</Author>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" ss:Size="11"/>
  </Style>
  <Style ss:ID="header">
   <Font ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#3D2E24" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  </Style>
  <Style ss:ID="alert">
   <Font ss:Bold="1" ss:Color="#B00020"/>
   <Alignment ss:WrapText="1"/>
  </Style>
  <Style ss:ID="money">
   <NumberFormat ss:Format="#,##0.00"/>
  </Style>
 </Styles>
${worksheets}
${buildSyntheseXml()}
 <Worksheet ss:Name="Mode emploi">
  <Table ss:ExpandedColumnCount="1" ss:ExpandedRowCount="22">
   <Column ss:Width="560"/>
   <Row><Cell><Data ss:Type="String">1. Un onglet par restaurant (${restaurants.map((r) => r.name).join(' · ')}).</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">2. Les 12 factures de 2026 sont déjà créées (date, n°, montant). Modifiez le montant en colonne C si besoin.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">3. Colonne « Payé ? » : mettez Oui quand le restaurant a réglé.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">4. Alerte facture : montant &gt; ${alertThreshold} € sur une ligne.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">5. Alerte encours : total impayé du restaurant &gt; ${alertThreshold} € → message de paiement.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">6. Onglet Synthèse : encours et alerte pour les deux clients.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">7. Ajoutez des lignes vides sous décembre pour des factures hors calendrier mensuel.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">Noms des onglets : modifiables dans tools/build-suivi-factures-xls.mjs puis régénérer le fichier.</Data></Cell></Row>
  </Table>
 </Worksheet>
</Workbook>
`;
}

const xml = buildWorkbookXml();
for (const outDir of outDirs) {
  fs.mkdirSync(outDir, { recursive: true });
  const outPath = path.join(outDir, 'suivi-factures-restaurants-pro.xls');
  fs.writeFileSync(outPath, xml, 'utf8');
  console.log('Written', outPath);
}
