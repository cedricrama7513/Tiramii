import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import * as XLSX from 'xlsx';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDirs = [
  path.join(__dirname, '..', 'outils'),
  path.join(__dirname, '..', 'a-telecharger'),
];

const maxRow = 120;
const alertThreshold = 500;
/** Versement standard quand le client recharge son compte. */
const depositAmount = 500;
/** Ligne de la dernière facture mensuelle préremplie (décembre = ligne 13). */
const lastPrefillRow = 13;

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
    openingBalance: Number(c.openingBalance ?? 0),
    depositMonths: (c.depositMonths || c.paidMonths || []).map(Number),
  }));
}

const restaurants = loadRestaurants();

const headers = [
  'Date facture',
  'N° facture',
  'Montant facture (€)',
  `Versement (€)`,
  'Solde (€)',
  'Alerte solde',
  'Payé ? (Oui / Non)',
  'Alerte facture > 500 €',
  'Encours impayé (€)',
  'Alerte encours',
  'Message envoyé ?',
  'Date relance',
];
/** Colonnes D–F : compte prépayé (mise en évidence). */
const soldeHeaderCols = new Set([4, 5, 6]);

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

/** Attribut XML (formules) : un seul passage, pas de &amp;lt; double. */
function escapeAttr(s) {
  return s
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function cell(row, col, type, value, extra = '') {
  if (type === 'formula') {
    return `<Cell ss:Index="${col}" ss:Formula="${escapeAttr(value)}"${extra}><Data ss:Type="String"></Data></Cell>`;
  }
  const dataType = type === 'number' ? 'Number' : 'String';
  const display = type === 'number' && value === '' ? '' : String(value);
  return `<Cell ss:Index="${col}"${extra}><Data ss:Type="${dataType}">${escapeXml(display)}</Data></Cell>`;
}

/** Formules solde : C vide = 0 en Excel (soustraction). */
function soldeFormulaFr(row, openingBalance) {
  const open = Number(openingBalance) || 0;
  if (row === 2) {
    return open > 0 ? `=${open}+D2-C2` : `=D2-C2`;
  }
  return `=E${row - 1}+D${row}-C${row}`;
}

function soldeFormulaEn(row, openingBalance) {
  const open = Number(openingBalance) || 0;
  if (row === 2) {
    return open > 0 ? `${open}+D2-C2` : `D2-C2`;
  }
  return `E${row - 1}+D${row}-C${row}`;
}

function alertSoldeFormulaFr(row) {
  return `=SI(E${row}<=0;"⚠ SOLDE ÉPUISÉ — faire verser ${depositAmount} €";"")`;
}

function alertSoldeFormulaEn(row) {
  return `IF(E${row}<=0,"⚠ SOLDE ÉPUISÉ — faire verser ${depositAmount} €","")`;
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
    const deposit = restaurant.depositMonths.includes(month) ? depositAmount : '';
    rows.push({ date, invNum, amount, deposit, paid, messageSent, relanceDate });
  }
  return rows;
}

function buildWorksheetXml(restaurant) {
  const prefill = buildInvoiceRows(restaurant);
  let rowsXml = '';

  rowsXml += '<Row ss:StyleID="header">';
  headers.forEach((h, i) => {
    const col = i + 1;
    const style = soldeHeaderCols.has(col) ? ' ss:StyleID="soldeHeader"' : ' ss:StyleID="header"';
    rowsXml += cell(1, col, 'string', h, style);
  });
  rowsXml += '</Row>\n';

  for (let r = 2; r <= maxRow; r++) {
    const data = prefill[r - 2];
    rowsXml += '<Row>';
    if (data) {
      rowsXml += cell(r, 1, 'string', data.date);
      rowsXml += cell(r, 2, 'string', data.invNum);
      // Colonne C : à remplir — le solde (E) se recalcule automatiquement
      rowsXml += cell(r, 3, 'string', '', ' ss:StyleID="inputMoney"');
      rowsXml += cell(r, 4, 'number', data.deposit !== '' ? data.deposit : 0, ' ss:StyleID="inputMoney"');
    } else {
      rowsXml += cell(r, 1, 'string', '');
      rowsXml += cell(r, 2, 'string', '');
      rowsXml += cell(r, 3, 'string', '', ' ss:StyleID="inputMoney"');
      rowsXml += cell(r, 4, 'number', 0, ' ss:StyleID="inputMoney"');
    }

    rowsXml += cell(
      r,
      5,
      'formula',
      soldeFormulaFr(r, restaurant.openingBalance),
      ' ss:StyleID="soldeMoney"'
    );
    rowsXml += cell(r, 6, 'formula', alertSoldeFormulaFr(r), ' ss:StyleID="alert"');

    if (data) {
      rowsXml += cell(r, 7, 'string', data.paid);
    } else {
      rowsXml += cell(r, 7, 'string', 'Non');
    }

    rowsXml += cell(
      r,
      8,
      'formula',
      `=SI(C${r}>${alertThreshold};"⚠ Facture > ${alertThreshold} € — à relancer";"")`,
      ' ss:StyleID="alert"'
    );
    rowsXml += cell(
      r,
      9,
      'formula',
      `=SOMME($C$2:$C$${maxRow})-SOMME.SI($C$2:$C$${maxRow};$G$2:$G$${maxRow};"Oui")`
    );
    rowsXml += cell(
      r,
      10,
      'formula',
      `=SI(I${r}>${alertThreshold};"⚠ ENVOYER MESSAGE PAIEMENT";"")`,
      ' ss:StyleID="alert"'
    );

    if (data) {
      rowsXml += cell(r, 11, 'string', data.messageSent);
      rowsXml += cell(r, 12, 'string', data.relanceDate);
    } else {
      rowsXml += cell(r, 11, 'string', 'Non');
      rowsXml += cell(r, 12, 'string', '');
    }
    rowsXml += '</Row>\n';
  }

  return ` <Worksheet ss:Name="${escapeXml(restaurant.sheetName)}">
  <Table ss:ExpandedColumnCount="12" ss:ExpandedRowCount="${maxRow}" x:FullColumns="1" x:FullRows="1" ss:DefaultColumnWidth="120">
   <Column ss:Width="95"/>
   <Column ss:Width="130"/>
   <Column ss:Width="95"/>
   <Column ss:Width="115"/>
   <Column ss:Width="105"/>
   <Column ss:Width="220"/>
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
  ['Restaurant', 'Solde crédit actuel (€)', 'Alerte solde', 'Encours impayé (€)', 'Alerte encours'].forEach(
    (h, i) => {
      synthRows += cell(1, i + 1, 'string', h, ' ss:StyleID="header"');
    }
  );
  synthRows += '</Row>\n';

  restaurants.forEach((restaurant, idx) => {
    const r = idx + 2;
    const ref = sheetRef(restaurant.sheetName);
    synthRows += '<Row>';
    synthRows += cell(r, 1, 'string', restaurant.name);
    synthRows += cell(r, 2, 'formula', `=${ref}!E${lastPrefillRow}`);
    synthRows += cell(
      r,
      3,
      'formula',
      `=SI(B${r}<=0;"⚠ RECHARGER ${depositAmount} €";"OK")`,
      ' ss:StyleID="alert"'
    );
    synthRows += cell(
      r,
      4,
      'formula',
      `=SOMME(${ref}!$C$2:$C$${maxRow})-SOMME.SI(${ref}!$C$2:$C$${maxRow};${ref}!$G$2:$G$${maxRow};"Oui")`
    );
    synthRows += cell(
      r,
      5,
      'formula',
      `=SI(D${r}>${alertThreshold};"⚠ RELANCER";"OK")`,
      ' ss:StyleID="alert"'
    );
    synthRows += '</Row>\n';
  });

  return ` <Worksheet ss:Name="Synthèse">
  <Table ss:ExpandedColumnCount="5" ss:ExpandedRowCount="10">
   <Column ss:Width="180"/>
   <Column ss:Width="120"/>
   <Column ss:Width="140"/>
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
 <ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
  <FullCalcOnLoad>1</FullCalcOnLoad>
 </ExcelWorkbook>
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
  <Style ss:ID="inputMoney">
   <NumberFormat ss:Format="#,##0.00"/>
   <Interior ss:Color="#FFFDE7" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="soldeHeader">
   <Font ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#1B5E20" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  </Style>
  <Style ss:ID="soldeMoney">
   <NumberFormat ss:Format="#,##0.00"/>
   <Interior ss:Color="#E8F5E9" ss:Pattern="Solid"/>
   <Font ss:Bold="1"/>
  </Style>
 </Styles>
${worksheets}
${buildSyntheseXml()}
 <Worksheet ss:Name="Mode emploi">
  <Table ss:ExpandedColumnCount="1" ss:ExpandedRowCount="26">
   <Column ss:Width="580"/>
   <Row><Cell><Data ss:Type="String">1. Un onglet par restaurant (${restaurants.map((r) => r.name).join(' · ')}).</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">2. Colonne C (jaune) : saisissez le montant de la facture → le solde (E) se recalcule tout seul.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">3. Colonne D (jaune) : versement client (${depositAmount} €). Formule solde : versements − factures.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">4. Alerte colonne F : dès que le solde ≤ 0 € → faire verser ${depositAmount} € au client.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">5. Saisissez ${depositAmount} en colonne D à chaque virement reçu (une ligne peut avoir 0 si pas de versement ce mois-là).</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">6. Colonne « Payé ? » (G) : facture réglée hors prépaiement (encours classique).</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">7. Onglet Synthèse : solde actuel (dernière ligne déc.) + alertes.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">Régénération : php tools/export-pro-clients-json.php puis node tools/build-suivi-factures-xls.mjs</Data></Cell></Row>
  </Table>
 </Worksheet>
</Workbook>
`;
}

function buildClientSheetXlsx(restaurant) {
  const prefill = buildInvoiceRows(restaurant);
  const rows = [headers];
  for (let r = 2; r <= maxRow; r++) {
    const data = prefill[r - 2];
    const row = Array(12).fill('');
    if (data) {
      row[0] = data.date;
      row[1] = data.invNum;
      row[3] = data.deposit !== '' ? data.deposit : 0;
      row[6] = data.paid;
      row[10] = data.messageSent;
      row[11] = data.relanceDate;
    } else {
      row[3] = 0;
      row[6] = 'Non';
      row[10] = 'Non';
    }
    rows.push(row);
  }
  const ws = XLSX.utils.aoa_to_sheet(rows);
  for (let r = 2; r <= maxRow; r++) {
    ws[`E${r}`] = { t: 'n', f: soldeFormulaEn(r, restaurant.openingBalance) };
    ws[`F${r}`] = { t: 's', f: alertSoldeFormulaEn(r) };
    ws[`H${r}`] = {
      t: 's',
      f: `IF(C${r}>${alertThreshold},"⚠ Facture > ${alertThreshold} € — à relancer","")`,
    };
    ws[`I${r}`] = {
      t: 'n',
      f: `SUM($C$2:$C$${maxRow})-SUMIF($G$2:$G$${maxRow},"Oui",$C$2:$C$${maxRow})`,
    };
    ws[`J${r}`] = {
      t: 's',
      f: `IF(I${r}>${alertThreshold},"⚠ ENVOYER MESSAGE PAIEMENT","")`,
    };
  }
  ws['!cols'] = [
    { wch: 12 },
    { wch: 18 },
    { wch: 10 },
    { wch: 14 },
    { wch: 12 },
    { wch: 28 },
    { wch: 10 },
    { wch: 26 },
    { wch: 14 },
    { wch: 26 },
    { wch: 14 },
    { wch: 12 },
  ];
  return ws;
}

function buildSyntheseSheetXlsx() {
  const synthHeaders = [
    'Restaurant',
    'Solde crédit actuel (€)',
    'Alerte solde',
    'Encours impayé (€)',
    'Alerte encours',
  ];
  const rows = [synthHeaders];
  restaurants.forEach((restaurant) => {
    rows.push([restaurant.name, '', '', '', '']);
  });
  const ws = XLSX.utils.aoa_to_sheet(rows);
  restaurants.forEach((restaurant, idx) => {
    const r = idx + 2;
    const ref = `'${restaurant.sheetName.replace(/'/g, "''")}'`;
    ws[`B${r}`] = { t: 'n', f: `${ref}!E${lastPrefillRow}` };
    ws[`C${r}`] = {
      t: 's',
      f: `IF(B${r}<=0,"⚠ RECHARGER ${depositAmount} €","OK")`,
    };
    ws[`D${r}`] = {
      t: 'n',
      f: `SUM(${ref}!$C$2:$C$${maxRow})-SUMIF(${ref}!$G$2:$G$${maxRow},"Oui",${ref}!$C$2:$C$${maxRow})`,
    };
    ws[`E${r}`] = { t: 's', f: `IF(D${r}>${alertThreshold},"⚠ RELANCER","OK")` };
  });
  return ws;
}

function buildWorkbookXlsx() {
  const wb = XLSX.utils.book_new();
  for (const restaurant of restaurants) {
    XLSX.utils.book_append_sheet(wb, buildClientSheetXlsx(restaurant), restaurant.sheetName);
  }
  XLSX.utils.book_append_sheet(wb, buildSyntheseSheetXlsx(), 'Synthèse');
  const help = [
    ['1. Un onglet par restaurant (' + restaurants.map((r) => r.name).join(' · ') + ').'],
    ['2. Colonne C : montant facture → le solde (E) se recalcule automatiquement.'],
    [`3. Colonne D : versement client (${depositAmount} €).`],
    ['4. Ouvrez ce fichier .xlsx avec Microsoft Excel (recommandé).'],
    ['5. Après saisie en C, appuyez sur Entrée si le solde ne bouge pas.'],
  ];
  XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(help), 'Mode emploi');
  return wb;
}

const xml = buildWorkbookXml();
const wbXlsx = buildWorkbookXlsx();
const baseName = 'suivi-factures-restaurants-pro';

for (const outDir of outDirs) {
  fs.mkdirSync(outDir, { recursive: true });
  const xlsPath = path.join(outDir, `${baseName}.xls`);
  const xlsxPath = path.join(outDir, `${baseName}.xlsx`);
  fs.writeFileSync(xlsPath, xml, 'utf8');
  XLSX.writeFile(wbXlsx, xlsxPath, { bookType: 'xlsx', cellStyles: false });
  console.log('Written', xlsPath);
  console.log('Written', xlsxPath);
}
