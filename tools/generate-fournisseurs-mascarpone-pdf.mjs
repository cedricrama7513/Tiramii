/**
 * Génère outils/fournisseurs-mascarpone-casa-dessert.pdf depuis le .md
 * Usage : node tools/generate-fournisseurs-mascarpone-pdf.mjs
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import PDFDocument from 'pdfkit';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const mdPath = path.join(root, 'outils', 'fournisseurs-mascarpone-casa-dessert.md');
const pdfPath = path.join(root, 'outils', 'fournisseurs-mascarpone-casa-dessert.pdf');
const aTelecharger = path.join(root, 'a-telecharger', 'fournisseurs-mascarpone-casa-dessert.pdf');

const md = fs.readFileSync(mdPath, 'utf8');
const lines = md.split(/\r?\n/);

const doc = new PDFDocument({
  size: 'A4',
  margins: { top: 52, bottom: 52, left: 48, right: 48 },
  info: {
    Title: 'Fournisseurs mascarpone - Casa Dessert',
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

const titleSize = 18;
const h2Size = 13;
const h3Size = 11;
const bodySize = 10;
const smallSize = 8.5;

function stripMd(s) {
  return s
    .replace(/\*\*(.+?)\*\*/g, '$1')
    .replace(/\*(.+?)\*/g, '$1')
    .replace(/`([^`]+)`/g, '$1')
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&');
}

function ensureSpace(need = 40) {
  if (doc.y + need > doc.page.height - doc.page.margins.bottom) {
    doc.addPage();
  }
}

function writeLine(text) {
  const raw = text.trimEnd();
  if (raw === '') {
    doc.moveDown(0.35);
    return;
  }

  if (raw.startsWith('# ')) {
    ensureSpace(48);
    doc.font('Helvetica-Bold').fontSize(titleSize).fillColor('#1a1a1a');
    doc.text(stripMd(raw.slice(2)), { align: 'left' });
    doc.moveDown(0.5);
    return;
  }
  if (raw.startsWith('## ')) {
    ensureSpace(34);
    doc.font('Helvetica-Bold').fontSize(h2Size).fillColor('#3d2e24');
    doc.text(stripMd(raw.slice(3)), { align: 'left' });
    doc.moveDown(0.3);
    doc
      .moveTo(doc.page.margins.left, doc.y)
      .lineTo(doc.page.width - doc.page.margins.right, doc.y)
      .strokeColor('#d4c4b0')
      .lineWidth(0.5)
      .stroke();
    doc.moveDown(0.35);
    return;
  }
  if (raw.startsWith('### ')) {
    ensureSpace(26);
    doc.font('Helvetica-Bold').fontSize(h3Size).fillColor('#1a1a1a');
    doc.text(stripMd(raw.slice(4)), { align: 'left' });
    doc.moveDown(0.2);
    return;
  }
  if (raw.startsWith('---')) {
    doc.moveDown(0.25);
    doc
      .moveTo(doc.page.margins.left, doc.y)
      .lineTo(doc.page.width - doc.page.margins.right, doc.y)
      .strokeColor('#e5e0d6')
      .stroke();
    doc.moveDown(0.35);
    return;
  }
  if (raw.startsWith('|')) {
    const cells = raw
      .split('|')
      .map((c) => stripMd(c.trim()))
      .filter((c) => c !== '');
    if (cells.every((c) => /^[-:]+$/.test(c))) return;
    ensureSpace(18);
    const isHeader =
      cells[0] === 'Priorité' ||
      cells[0] === 'Fournisseur' ||
      cells[0] === 'Prix d’achat' ||
      cells[0] === "Prix d'achat" ||
      cells[0] === 'Structure';
    doc.font(isHeader ? 'Helvetica-Bold' : 'Helvetica').fontSize(smallSize).fillColor('#333');
    doc.text(cells.join('  ·  '), { lineGap: 1.5 });
    return;
  }
  if (raw.startsWith('- ')) {
    ensureSpace(20);
    doc.font('Helvetica').fontSize(bodySize).fillColor('#333');
    doc.text('• ' + stripMd(raw.slice(2)), { indent: 10, lineGap: 2 });
    return;
  }
  if (/^\d+\.\s/.test(raw)) {
    ensureSpace(20);
    doc.font('Helvetica').fontSize(bodySize).fillColor('#333');
    doc.text(stripMd(raw), { indent: 10, lineGap: 2 });
    return;
  }
  if (raw.startsWith('*') && raw.endsWith('*')) {
    ensureSpace(18);
    doc.font('Helvetica-Oblique').fontSize(smallSize).fillColor('#555');
    doc.text(stripMd(raw.replace(/^\*|\*$/g, '')), { lineGap: 2 });
    return;
  }

  ensureSpace(22);
  doc.font('Helvetica').fontSize(bodySize).fillColor('#333');
  doc.text(stripMd(raw), { lineGap: 2.5 });
}

for (const line of lines) {
  writeLine(line);
}

doc.end();
