/**
 * Bulletin de paie type — pâtissier 35 h — Casa Dessert
 * Usage : node tools/generate-fiche-paie-patissier.mjs
 *
 * Simulation gestionnaire de paie (nov. 2026). À faire valider par un expert-comptable
 * avant remise officielle (convention collective, mutuelle, taux AT, PAS, etc.).
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import PDFDocument from 'pdfkit';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const pdfPath = path.join(root, 'outils', 'fiche-paie-patissier-35h-casa-dessert.pdf');
const aTelecharger = path.join(root, 'a-telecharger', 'fiche-paie-patissier-35h-casa-dessert.pdf');
const mdPath = path.join(root, 'outils', 'fiche-paie-patissier-35h-casa-dessert.md');

// —— Paramètres bulletin (nov. 2026) ——
const periode = '01/11/2026 → 30/11/2026';
const paiement = '30/11/2026';
const heuresMensuelles = 151.67; // 35 h / semaine
const tauxHoraire = 12.31; // SMIC horaire brut au 01/06/2026
const brut = Math.round(heuresMensuelles * tauxHoraire * 100) / 100; // 1 867,02

const assietteCsg = Math.round(brut * 0.9825 * 100) / 100;

const cotisSalariales = [
  { lib: 'Sécurité sociale — vieillesse plafonnée', base: brut, taux: 6.9 },
  { lib: 'Sécurité sociale — vieillesse déplafonnée', base: brut, taux: 0.4 },
  { lib: 'Retraite complémentaire Agirc-Arrco T1', base: brut, taux: 3.15 },
  { lib: 'Contribution d’équilibre général (CEG) T1', base: brut, taux: 0.86 },
  { lib: 'CSG déductible', base: assietteCsg, taux: 6.8 },
  { lib: 'CSG non déductible', base: assietteCsg, taux: 2.4 },
  { lib: 'CRDS non déductible', base: assietteCsg, taux: 0.5 },
];

const cotisPatronales = [
  { lib: 'Assurance maladie', base: brut, taux: 13.0 },
  { lib: 'Vieillesse plafonnée', base: brut, taux: 8.55 },
  { lib: 'Vieillesse déplafonnée', base: brut, taux: 2.11 },
  { lib: 'Allocations familiales', base: brut, taux: 5.25 },
  { lib: 'Accident du travail (hypothèse)', base: brut, taux: 1.5 },
  { lib: 'Assurance chômage', base: brut, taux: 4.0 },
  { lib: 'AGS', base: brut, taux: 0.25 },
  { lib: 'FNAL (< 50 salariés)', base: brut, taux: 0.1 },
  { lib: 'Agirc-Arrco T1', base: brut, taux: 4.72 },
  { lib: 'CEG T1', base: brut, taux: 1.29 },
  { lib: 'Contribution solidarité autonomie', base: brut, taux: 0.3 },
  { lib: 'Dialogue social', base: brut, taux: 0.016 },
  { lib: 'Formation professionnelle (< 11 sal.)', base: brut, taux: 0.55 },
];

function mt(base, taux) {
  return Math.round(base * (taux / 100) * 100) / 100;
}

const lignesSal = cotisSalariales.map((c) => ({ ...c, montant: mt(c.base, c.taux) }));
const lignesPat = cotisPatronales.map((c) => ({ ...c, montant: mt(c.base, c.taux) }));
const totalSal = Math.round(lignesSal.reduce((s, l) => s + l.montant, 0) * 100) / 100;
const totalPat = Math.round(lignesPat.reduce((s, l) => s + l.montant, 0) * 100) / 100;

const netAvantImpot = Math.round((brut - totalSal) * 100) / 100;
const csgNd = lignesSal.find((l) => l.lib.startsWith('CSG non')).montant;
const crds = lignesSal.find((l) => l.lib.startsWith('CRDS')).montant;
const netImposable = Math.round((netAvantImpot + csgNd + crds) * 100) / 100;
const tauxPas = 0; // à renseigner selon taux personnalisé DGFiP
const pas = Math.round(netImposable * (tauxPas / 100) * 100) / 100;
const netAPayer = Math.round((netAvantImpot - pas) * 100) / 100;

// Réduction générale (ordre de grandeur au SMIC — à recalculer par le cabinet)
const reductionGeneraleHyp = 700.13;
const coutEmployeur =
  Math.round((brut + totalPat - reductionGeneraleHyp) * 100) / 100;

function euro(n) {
  return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

function pct(n) {
  return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 3 }) + ' %';
}

// —— Markdown récap ——
const md = `# Bulletin de paie — Pâtissier 35 h — Casa Dessert

**Statut :** projet / simulation gestionnaire de paie  
**Période :** ${periode}  
**À faire valider** par un expert-comptable avant remise officielle au salarié.

## Employeur

| | |
|--|--|
| Enseigne | Casa Dessert |
| Exploitant | Cedric Ramahefaharison (entrepreneur individuel) |
| SIREN | 944 172 139 |
| Adresse | 60 rue François 1er, 75008 Paris |
| Convention | Boulangerie-pâtisserie artisanale (à confirmer — IDCC) |

## Salarié (à personnaliser)

| | |
|--|--|
| Nom / prénom | **[À COMPLÉTER]** |
| Emploi | Pâtissier / préparateur desserts |
| Contrat | CDI — **35 h / semaine** (151,67 h / mois) |
| Classification | Non cadre |
| Ancienneté | [À COMPLÉTER] |
| N° Sécurité sociale | [À COMPLÉTER] |

## Rémunération

| Rubrique | Base / qty | Taux | Montant |
|----------|------------|------|---------|
| Salaire de base | ${heuresMensuelles.toLocaleString('fr-FR')} h | ${euro(tauxHoraire)} / h | **${euro(brut)}** |
| **Salaire brut** | | | **${euro(brut)}** |

*Base retenue : SMIC horaire brut au 1er juin 2026 (${euro(tauxHoraire)}). À augmenter si classification conventionnelle supérieure.*

## Cotisations salariales

| Rubrique | Base | Taux | Montant |
|----------|------|------|---------|
${lignesSal.map((l) => `| ${l.lib} | ${euro(l.base)} | ${pct(l.taux)} | ${euro(l.montant)} |`).join('\n')}
| **Total retenues** | | | **${euro(totalSal)}** |

## Nets

| | Montant |
|--|---------|
| Net avant impôt (net social) | **${euro(netAvantImpot)}** |
| Net imposable | ${euro(netImposable)} |
| Prélèvement à la source (${pct(tauxPas)} — à renseigner) | ${euro(pas)} |
| **Net à payer** | **${euro(netAPayer)}** |

## Cotisations patronales (indicatif)

| Rubrique | Base | Taux | Montant |
|----------|------|------|---------|
${lignesPat.map((l) => `| ${l.lib} | ${euro(l.base)} | ${pct(l.taux)} | ${euro(l.montant)} |`).join('\n')}
| Total brut patronal | | | ${euro(totalPat)} |
| Réduction générale (hypothèse SMIC) | | | − ${euro(reductionGeneraleHyp)} |
| **Coût employeur estimé** | | | **${euro(coutEmployeur)}** |

## Paiement

- Date de paiement : ${paiement}
- Mode : virement bancaire
- IBAN salarié : [À COMPLÉTER]

## Mentions

Ce document est une **simulation** établie pour Casa Dessert. Il ne remplace pas un logiciel de paie agréé ni le contrôle d’un expert-comptable (mutuelle, prévoyance, titres-restaurant, acomptes, heures supp., taux PAS notifié, etc.).
`;

fs.writeFileSync(mdPath, md, 'utf8');
console.log('Written', mdPath);

// —— PDF ——
const doc = new PDFDocument({
  size: 'A4',
  margins: { top: 36, bottom: 36, left: 40, right: 40 },
  info: {
    Title: 'Bulletin de paie - Patissier 35h - Casa Dessert',
    Author: 'Casa Dessert',
  },
});

const chunks = [];
doc.on('data', (c) => chunks.push(c));
doc.on('end', () => {
  const buf = Buffer.concat(chunks);
  for (const p of [pdfPath, aTelecharger]) {
    fs.mkdirSync(path.dirname(p), { recursive: true });
    fs.writeFileSync(p, buf);
    console.log('Written', p);
  }
});

const left = doc.page.margins.left;
const right = doc.page.width - doc.page.margins.right;
const width = right - left;

function lineY(y) {
  doc
    .moveTo(left, y)
    .lineTo(right, y)
    .strokeColor('#ccc')
    .lineWidth(0.4)
    .stroke();
}

function row(cols, y, opts = {}) {
  const { bold = false, size = 8, fill = '#111' } = opts;
  doc.font(bold ? 'Helvetica-Bold' : 'Helvetica').fontSize(size).fillColor(fill);
  const xs = [left, left + width * 0.48, left + width * 0.68, left + width * 0.84];
  cols.forEach((t, i) => {
    const align = i === 0 ? 'left' : 'right';
    const w = i === 0 ? width * 0.46 : width * 0.15;
    doc.text(String(t), xs[i], y, { width: w, align, lineBreak: false });
  });
  return y + 12;
}

// En-tête
doc.rect(left, 36, width, 28).fill('#3d2e24');
doc.fillColor('#fff').font('Helvetica-Bold').fontSize(14);
doc.text('BULLETIN DE PAIE', left + 10, 44, { continued: false });
doc.font('Helvetica').fontSize(8);
doc.text('PROJET / SIMULATION — à valider par expert-comptable', left + 160, 48);

doc.fillColor('#111');
let y = 78;
doc.font('Helvetica-Bold').fontSize(10).text('Casa Dessert', left, y);
doc.font('Helvetica').fontSize(8);
y += 12;
doc.text('Cedric Ramahefaharison — Entrepreneur individuel', left, y);
y += 10;
doc.text('SIREN 944 172 139 — 60 rue François 1er, 75008 Paris', left, y);
y += 10;
doc.text('Emploi : Pâtissier / préparateur desserts — Non cadre — 35 h / sem.', left, y);

doc.font('Helvetica-Bold').fontSize(9);
doc.text(`Période : ${periode}`, left + width * 0.55, 78, { width: width * 0.45, align: 'right' });
doc.font('Helvetica').fontSize(8);
doc.text(`Paiement : ${paiement}`, left + width * 0.55, 90, { width: width * 0.45, align: 'right' });
doc.text('Salarié : [À COMPLÉTER]', left + width * 0.55, 102, { width: width * 0.45, align: 'right' });

y = 130;
lineY(y);
y += 6;

doc.font('Helvetica-Bold').fontSize(9).fillColor('#3d2e24').text('1. Rémunération', left, y);
y += 14;
y = row(['Libellé', 'Base / Qté', 'Taux / unit.', 'Montant'], y, { bold: true, size: 7.5, fill: '#555' });
y = row(
  ['Salaire de base (SMIC 06/2026)', `${heuresMensuelles.toLocaleString('fr-FR')} h`, euro(tauxHoraire) + '/h', euro(brut)],
  y,
  { size: 8 }
);
y = row(['SALAIRE BRUT', '', '', euro(brut)], y, { bold: true, size: 9 });
y += 6;
lineY(y);
y += 8;

doc.font('Helvetica-Bold').fontSize(9).fillColor('#3d2e24').text('2. Cotisations salariales', left, y);
y += 14;
y = row(['Libellé', 'Base', 'Taux', 'Salarié'], y, { bold: true, size: 7.5, fill: '#555' });
for (const l of lignesSal) {
  y = row([l.lib, euro(l.base), pct(l.taux), euro(l.montant)], y, { size: 7.5 });
}
y = row(['TOTAL RETENUES SALARIALES', '', '', euro(totalSal)], y, { bold: true, size: 8 });
y += 6;
lineY(y);
y += 8;

doc.font('Helvetica-Bold').fontSize(9).fillColor('#3d2e24').text('3. Net à payer', left, y);
y += 14;
doc.font('Helvetica').fontSize(8).fillColor('#111');
doc.text(`Net avant impôt (net social) : ${euro(netAvantImpot)}`, left, y);
y += 11;
doc.text(`Net imposable : ${euro(netImposable)}`, left, y);
y += 11;
doc.text(`Prélèvement à la source (${pct(tauxPas)} — taux DGFiP à renseigner) : ${euro(pas)}`, left, y);
y += 14;
doc.rect(left, y - 2, width, 22).fill('#1b5e20');
doc.fillColor('#fff').font('Helvetica-Bold').fontSize(11);
doc.text(`NET À PAYER AU SALARIÉ : ${euro(netAPayer)}`, left + 10, y + 4);
y += 30;

doc.fillColor('#111').font('Helvetica-Bold').fontSize(9).fillColor('#3d2e24').text('4. Cotisations patronales (indicatif)', left, y);
y += 14;
y = row(['Libellé', 'Base', 'Taux', 'Employeur'], y, { bold: true, size: 7.5, fill: '#555' });
for (const l of lignesPat) {
  if (y > 720) {
    doc.addPage();
    y = 50;
  }
  y = row([l.lib, euro(l.base), pct(l.taux), euro(l.montant)], y, { size: 7 });
}
y = row(['Total cotisations patronales', '', '', euro(totalPat)], y, { bold: true, size: 8 });
y = row(['Réduction générale (hypothèse SMIC)', '', '', '− ' + euro(reductionGeneraleHyp)], y, {
  size: 8,
});
y = row(['COÛT EMPLOYEUR ESTIMÉ', '', '', euro(coutEmployeur)], y, { bold: true, size: 9 });
y += 10;
lineY(y);
y += 10;

doc.font('Helvetica').fontSize(7).fillColor('#444');
doc.text(
  'Convention collective : boulangerie-pâtisserie artisanale à confirmer. Mutuelle / prévoyance / titres-restaurant non inclus. ' +
    'Taux AT (1,50 %) hypothétique. Réduction générale estimative. ' +
    'Document établi pour Casa Dessert — ne constitue pas un bulletin officiel tant qu’il n’est pas validé et édité via un logiciel de paie conforme.',
  left,
  y,
  { width, align: 'left', lineGap: 1.5 }
);
y += 36;
doc.font('Helvetica').fontSize(8).fillColor('#111');
doc.text('Signature employeur : ________________', left, y);
doc.text('Pour le salarié (accusé) : ________________', left + width * 0.5, y);

doc.end();
