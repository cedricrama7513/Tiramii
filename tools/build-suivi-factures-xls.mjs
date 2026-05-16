import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(__dirname, '..', 'outils');
fs.mkdirSync(outDir, { recursive: true });

const maxRow = 500;
const headers = [
  'Restaurant',
  'Date facture',
  'N° facture',
  'Montant TTC (€)',
  'Payé ? (Oui / Non)',
  'Alerte si facture > 500 €',
  'Encours impayé restaurant (€)',
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

function cell(row, col, type, value, extra = '') {
  const ref = `${colLetter(col)}${row}`;
  if (type === 'formula') {
    return `<Cell ss:Index="${col}" ss:Formula="${escapeXml(value)}"><Data ss:Type="String"></Data></Cell>`;
  }
  const dataType = type === 'number' ? 'Number' : 'String';
  return `<Cell ss:Index="${col}"${extra}><Data ss:Type="${dataType}">${escapeXml(String(value))}</Data></Cell>`;
}

function escapeXml(s) {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

const exampleRows = [
  ['Le Petit Bistrot', '2026-05-01', 'FAC-2026-042', 320, 'Non', '', '', '', 'Non', ''],
  ['Le Petit Bistrot', '2026-05-10', 'FAC-2026-051', 280, 'Non', '', '', '', 'Non', ''],
  ['Salon Thé Marais', '2026-04-15', 'FAC-2026-038', 150, 'Oui', '', '', '', 'Oui', '2026-04-20'],
  ['Brasserie Sud', '2026-05-12', 'FAC-2026-055', 620, 'Non', '', '', '', 'Non', ''],
];

let rowsXml = '';

// Header
rowsXml += '<Row ss:StyleID="header">';
headers.forEach((h, i) => {
  rowsXml += cell(1, i + 1, 'string', h, ' ss:StyleID="header"');
});
rowsXml += '</Row>\n';

for (let r = 2; r <= maxRow; r++) {
  const ex = exampleRows[r - 2];
  rowsXml += '<Row>';
  if (ex) {
    rowsXml += cell(r, 1, 'string', ex[0]);
    rowsXml += cell(r, 2, 'string', ex[1]);
    rowsXml += cell(r, 3, 'string', ex[2]);
    rowsXml += cell(r, 4, 'number', ex[3]);
    rowsXml += cell(r, 5, 'string', ex[4]);
  } else {
    rowsXml += cell(r, 1, 'string', '');
    rowsXml += cell(r, 2, 'string', '');
    rowsXml += cell(r, 3, 'string', '');
    rowsXml += cell(r, 4, 'number', '');
    rowsXml += cell(r, 5, 'string', 'Non');
  }
  // F: alerte facture > 500
  rowsXml += cell(
    r,
    6,
    'formula',
    `=SI(D${r}&gt;500;&quot;⚠ Facture &gt; 500 € — à relancer&quot;;&quot;&quot;)`,
    ' ss:StyleID="alert"'
  );
  // G: encours impayé par restaurant
  rowsXml += cell(
    r,
    7,
    'formula',
    `=SOMME.SI($D$2:$D$${maxRow};$A$2:$A$${maxRow};A${r})-SOMME.SI.ENS($D$2:$D$${maxRow};$A$2:$A$${maxRow};A${r};$E$2:$E$${maxRow};&quot;Oui&quot;)`
  );
  // H: alerte encours > 500
  rowsXml += cell(
    r,
    8,
    'formula',
    `=SI(G${r}&gt;500;&quot;⚠ ENVOYER MESSAGE PAIEMENT&quot;;&quot;&quot;)`,
    ' ss:StyleID="alert"'
  );
  if (ex) {
    rowsXml += cell(r, 9, 'string', ex[8]);
    rowsXml += cell(r, 10, 'string', ex[9]);
  } else {
    rowsXml += cell(r, 9, 'string', 'Non');
    rowsXml += cell(r, 10, 'string', '');
  }
  rowsXml += '</Row>\n';
}

// Synthèse restaurants
let synthRows = '';
synthRows += '<Row ss:StyleID="header">';
['Restaurant', 'Encours impayé (€)', 'Alerte > 500 €'].forEach((h, i) => {
  synthRows += cell(1, i + 1, 'string', h, ' ss:StyleID="header"');
});
synthRows += '</Row>\n';

const restaurants = ['Le Petit Bistrot', 'Salon Thé Marais', 'Brasserie Sud', ''];
for (let r = 2; r <= 30; r++) {
  const name = restaurants[r - 2] ?? '';
  synthRows += '<Row>';
  synthRows += cell(r, 1, 'string', name);
  synthRows += cell(
    r,
    2,
    'formula',
    name
      ? `=SOMME.SI(Suivi!$D$2:$D$${maxRow};Suivi!$A$2:$A$${maxRow};A${r})-SOMME.SI.ENS(Suivi!$D$2:$D$${maxRow};Suivi!$A$2:$A$${maxRow};A${r};Suivi!$E$2:$E$${maxRow};&quot;Oui&quot;)`
      : ''
  );
  synthRows += cell(
    r,
    3,
    'formula',
    name ? `=SI(B${r}&gt;500;&quot;⚠ RELANCER&quot;;&quot;OK&quot;)` : '',
    ' ss:StyleID="alert"'
  );
  synthRows += '</Row>\n';
}

const xml = `<?xml version="1.0" encoding="UTF-8"?>
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
   <NumberFormat ss:Format="Standard"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Suivi">
  <Table ss:ExpandedColumnCount="10" ss:ExpandedRowCount="${maxRow}" x:FullColumns="1" x:FullRows="1" ss:DefaultColumnWidth="120">
   <Column ss:Width="160"/>
   <Column ss:Width="95"/>
   <Column ss:Width="110"/>
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
 </Worksheet>
 <Worksheet ss:Name="Synthèse">
  <Table ss:ExpandedColumnCount="3" ss:ExpandedRowCount="30">
   <Column ss:Width="180"/>
   <Column ss:Width="120"/>
   <Column ss:Width="120"/>
${synthRows}
  </Table>
 </Worksheet>
 <Worksheet ss:Name="Mode emploi">
  <Table ss:ExpandedColumnCount="1" ss:ExpandedRowCount="20">
   <Column ss:Width="520"/>
   <Row><Cell><Data ss:Type="String">1. Saisissez une ligne par facture (onglet Suivi).</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">2. Colonne « Payé ? » : mettez Oui quand le restaurant a réglé.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">3. Alerte facture : une ligne dont le montant dépasse 500 €.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">4. Alerte encours : total impayé du restaurant &gt; 500 € → envoyez votre message de paiement.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">5. Onglet Synthèse : vue par restaurant (encours + alerte).</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">6. Colonnes I–J : cochez « Message envoyé » et la date de relance après contact.</Data></Cell></Row>
   <Row><Cell><Data ss:Type="String">Seuil modifiable : remplacez 500 par un autre nombre dans les formules (colonnes F, H, Synthèse).</Data></Cell></Row>
  </Table>
 </Worksheet>
</Workbook>
`;

const outPath = path.join(outDir, 'suivi-factures-restaurants-pro.xls');
fs.writeFileSync(outPath, xml, 'utf8');
console.log('Written', outPath);
