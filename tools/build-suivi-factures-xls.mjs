/**
 * Génère suivi-factures-restaurants-pro.xlsx (formules Excel natives, Node uniquement).
 * Prérequis : npm install xlsx (dans tools/)
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import XLSX from 'xlsx';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDirs = [
  path.join(__dirname, '..', 'outils'),
  path.join(__dirname, '..', 'a-telecharger'),
];
const clientsJsonPath = path.join(__dirname, 'pro-clients.json');
const fileName = 'suivi-factures-restaurants-pro.xlsx';

const maxRow = 120;
const alertThreshold = 500;
const depositAmount = 500;
const lastPrefillRow = 13;

const headers = [
  'Date facture',
  'N° facture',
  'Montant facture (€)',
  'Versement (€)',
  'Solde (€)',
  'Alerte solde',
  'Payé ? (Oui / Non)',
  'Alerte facture > 500 €',
  'Encours impayé (€)',
  'Alerte encours',
  'Message envoyé ?',
  'Date relance',
];

function loadRestaurants() {
  if (!fs.existsSync(clientsJsonPath)) {
    throw new Error(`Fichier ${clientsJsonPath} introuvable.`);
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
    paidMonths: (c.paidMonths || []).map(Number),
    openingBalance: Number(c.openingBalance ?? 0),
    depositMonths: (c.depositMonths || c.paidMonths || []).map(Number),
  }));
}

function pad2(n) {
  return String(n).padStart(2, '0');
}

function buildInvoiceRows(restaurant) {
  const rows = [];
  const paidMonths = new Set(restaurant.paidMonths);
  const depositMonths = new Set(restaurant.depositMonths);
  for (let month = 1; month <= 12; month++) {
    const paid = paidMonths.has(month);
    rows.push({
      date: `2026-${pad2(month)}-01`,
      inv: `${restaurant.invoicePrefix}-2026-${pad2(month)}`,
      deposit: depositMonths.has(month) ? depositAmount : 0,
      paid: paid ? 'Oui' : 'Non',
      message: paid ? 'Oui' : 'Non',
      relance: paid ? `2026-${pad2(month)}-15` : '',
    });
  }
  return rows;
}

/** Formules en anglais (Excel FR les traduit à l’affichage). */
function soldeFormula(row, opening) {
  if (opening > 0) {
    return `=${opening}+SUM($D$2:D${row})-SUM($C$2:C${row})`;
  }
  return `=SUM($D$2:D${row})-SUM($C$2:C${row})`;
}

function previewSolde(prefill, row, opening) {
  let depSum = 0;
  for (let i = 0; i < row - 2 && i < prefill.length; i++) {
    depSum += prefill[i].deposit;
  }
  return Math.round((opening + depSum) * 100) / 100;
}

function sheetRef(name) {
  return `'${name.replace(/'/g, "''")}'`;
}

function setFormula(ws, row, col, formula, cached, type = 's') {
  const addr = XLSX.utils.encode_cell({ r: row, c: col });
  const cell = { f: formula };
  if (cached !== undefined && cached !== '') {
    cell.v = cached;
    cell.t = typeof cached === 'number' ? 'n' : type;
  }
  ws[addr] = cell;
}

function buildRestaurantSheet(restaurant) {
  const prefill = buildInvoiceRows(restaurant);
  const opening = restaurant.openingBalance;
  const aoa = [headers];
  for (let r = 2; r <= maxRow; r++) {
    aoa.push(new Array(headers.length).fill(''));
  }
  const ws = XLSX.utils.aoa_to_sheet(aoa);

  for (let r = 2; r <= maxRow; r++) {
    const xr = r - 1;
    const data = prefill[r - 2];

    if (data) {
      ws[XLSX.utils.encode_cell({ r: xr, c: 0 })] = { t: 's', v: data.date };
      ws[XLSX.utils.encode_cell({ r: xr, c: 1 })] = { t: 's', v: data.inv };
    }
    ws[XLSX.utils.encode_cell({ r: xr, c: 2 })] = { t: 'n', v: 0 };
    ws[XLSX.utils.encode_cell({ r: xr, c: 3 })] = {
      t: 'n',
      v: data ? data.deposit : 0,
    };

    setFormula(ws, xr, 4, soldeFormula(r, opening), previewSolde(prefill, r, opening), 'n');
    setFormula(
      ws,
      xr,
      5,
      `=IF(E${r}<=0,"⚠ SOLDE ÉPUISÉ — faire verser ${depositAmount} €","")`,
      ''
    );
    ws[XLSX.utils.encode_cell({ r: xr, c: 6 })] = {
      t: 's',
      v: data ? data.paid : 'Non',
    };
    setFormula(
      ws,
      xr,
      7,
      `=IF(C${r}>${alertThreshold},"⚠ Facture > ${alertThreshold} € — à relancer","")`,
      ''
    );
    setFormula(
      ws,
      xr,
      8,
      `=SUM($C$2:$C$${maxRow})-SUMIF($G$2:$G$${maxRow},"Oui",$C$2:$C$${maxRow})`,
      0,
      'n'
    );
    setFormula(
      ws,
      xr,
      9,
      `=IF(I${r}>${alertThreshold},"⚠ ENVOYER MESSAGE PAIEMENT","")`,
      ''
    );
    ws[XLSX.utils.encode_cell({ r: xr, c: 10 })] = {
      t: 's',
      v: data ? data.message : 'Non',
    };
    if (data?.relance) {
      ws[XLSX.utils.encode_cell({ r: xr, c: 11 })] = { t: 's', v: data.relance };
    }
  }

  ws['!cols'] = [
    { wch: 12 },
    { wch: 18 },
    { wch: 11 },
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
  ws['!freeze'] = { xSplit: 0, ySplit: 1, topLeftCell: 'A2', activePane: 'bottomLeft', state: 'frozen' };
  return ws;
}

const restaurants = loadRestaurants();
const wb = XLSX.utils.book_new();

for (const restaurant of restaurants) {
  XLSX.utils.book_append_sheet(wb, buildRestaurantSheet(restaurant), restaurant.sheetName);
}

const synHeaders = [
  'Restaurant',
  'Solde crédit actuel (€)',
  'Alerte solde',
  'Encours impayé (€)',
  'Alerte encours',
];
const synAoa = [synHeaders];
for (const r of restaurants) {
  synAoa.push([r.name, '', '', '', '']);
}
const wsSyn = XLSX.utils.aoa_to_sheet(synAoa);

restaurants.forEach((restaurant, idx) => {
  const row = idx + 1;
  const excelRow = row + 1;
  const ref = sheetRef(restaurant.sheetName);
  const prefill = buildInvoiceRows(restaurant);
  const cachedE = previewSolde(prefill, lastPrefillRow, restaurant.openingBalance);
  setFormula(wsSyn, row, 1, `=${ref}!E${lastPrefillRow}`, cachedE, 'n');
  setFormula(
    wsSyn,
    row,
    2,
    `=IF(B${excelRow}<=0,"⚠ RECHARGER ${depositAmount} €","OK")`,
    'OK'
  );
  setFormula(
    wsSyn,
    row,
    3,
    `=SUM(${ref}!$C$2:$C$${maxRow})-SUMIF(${ref}!$G$2:$G$${maxRow},"Oui",${ref}!$C$2:$C$${maxRow})`,
    0,
    'n'
  );
  setFormula(
    wsSyn,
    row,
    4,
    `=IF(D${excelRow}>${alertThreshold},"⚠ RELANCER","OK")`,
    'OK'
  );
});
wsSyn['!cols'] = [{ wch: 24 }, { wch: 16 }, { wch: 18 }, { wch: 14 }, { wch: 14 }];
XLSX.utils.book_append_sheet(wb, wsSyn, 'Synthèse');

const names = restaurants.map((r) => r.name).join(' · ');
const help = [
  [`Suivi factures — ${names}`],
  [''],
  ['1. Un onglet par restaurant.'],
  ['2. Colonne C : montant de la facture → solde (E) se met à jour.'],
  [`3. Colonne D : versement client (${depositAmount} €).`],
  ['4. EXCEL MAC : cliquez « Activer la modification » (bannière jaune).'],
  ['5. Saisissez le montant en C puis Entrée ou Tab.'],
  ['6. Alerte colonne F : solde ≤ 0 → faire verser 500 €.'],
  ['7. Alerte colonne J : encours impayé > 500 € → relancer le restaurant.'],
  ['8. Téléchargez toujours le .xlsx sur casadessert.fr/outils/'],
];
XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(help), 'Mode emploi');

wb.Workbook = wb.Workbook || {};
wb.Workbook.CalcPr = { fullCalcOnLoad: true };

const buf = XLSX.write(wb, { type: 'buffer', bookType: 'xlsx', cellStyles: false });

for (const outDir of outDirs) {
  fs.mkdirSync(outDir, { recursive: true });
  const target = path.join(outDir, fileName);
  fs.writeFileSync(target, buf);
  console.log('Written', target);
  const oldXls = path.join(outDir, 'suivi-factures-restaurants-pro.xls');
  if (fs.existsSync(oldXls)) {
    fs.unlinkSync(oldXls);
    console.log('Removed', oldXls);
  }
}
