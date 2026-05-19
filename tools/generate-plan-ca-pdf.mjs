/**
 * Génère outils/plan-action-ca-casa-dessert.pdf depuis le .md
 * Usage : node tools/generate-plan-ca-pdf.mjs
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import PDFDocument from 'pdfkit';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const mdPath = path.join(root, 'outils', 'plan-action-ca-casa-dessert.md');
const pdfPath = path.join(root, 'outils', 'plan-action-ca-casa-dessert.pdf');
const aTelecharger = path.join(root, 'a-telecharger', 'plan-action-ca-casa-dessert.pdf');

const md = fs.readFileSync(mdPath, 'utf8');
const lines = md.split(/\r?\n/);

const doc = new PDFDocument({
  size: 'A4',
  margins: { top: 56, bottom: 56, left: 56, right: 56 },
  info: {
    Title: 'Plan d action CA - Casa Dessert',
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

const titleSize = 20;
const h2Size = 14;
const h3Size = 12;
const bodySize = 10.5;
const smallSize = 9;
let inTable = false;

function ensureSpace(need = 40) {
  if (doc.y + need > doc.page.height - doc.page.margins.bottom) {
    doc.addPage();
  }
}

function writeLine(text, opts = {}) {
  const t = text.trimEnd();
  if (t === '') {
    doc.moveDown(0.4);
    return;
  }

  if (t.startsWith('# ')) {
    ensureSpace(50);
    doc.font('Helvetica-Bold').fontSize(titleSize).fillColor('#1a1a1a');
    doc.text(t.slice(2), { align: 'left' });
    doc.moveDown(0.6);
    return;
  }
  if (t.startsWith('## ')) {
    ensureSpace(36);
    doc.font('Helvetica-Bold').fontSize(h2Size).fillColor('#3d2e24');
    doc.text(t.slice(3), { align: 'left' });
    doc.moveDown(0.35);
    doc.moveTo(doc.page.margins.left, doc.y).lineTo(doc.page.width - doc.page.margins.right, doc.y)
      .strokeColor('#d4c4b0').lineWidth(0.5).stroke();
    doc.moveDown(0.4);
    return;
  }
  if (t.startsWith('### ')) {
    ensureSpace(28);
    doc.font('Helvetica-Bold').fontSize(h3Size).fillColor('#1a1a1a');
    doc.text(t.slice(4), { align: 'left' });
    doc.moveDown(0.25);
    return;
  }
  if (t.startsWith('---')) {
    doc.moveDown(0.3);
    doc.moveTo(doc.page.margins.left, doc.y).lineTo(doc.page.width - doc.page.margins.right, doc.y)
      .strokeColor('#e5e0d6').stroke();
    doc.moveDown(0.4);
    return;
  }
  if (t.startsWith('|')) {
    inTable = true;
    doc.font('Helvetica').fontSize(smallSize).fillColor('#333');
    const cells = t.split('|').map((c) => c.trim()).filter((c) => c !== '');
    if (cells.every((c) => /^[-:]+$/.test(c))) return;
    const isHeader = !inTable || doc.y < 120;
    if (cells[0] && cells[0].includes('Canal')) {
      doc.font('Helvetica-Bold');
    } else if (cells[0] && (cells[0].includes('Semaine') || cells[0].includes('Horizon'))) {
      doc.font('Helvetica-Bold');
    } else {
      doc.font('Helvetica');
    }
    doc.text(cells.join('  |  '), { lineGap: 2 });
    return;
  }
  if (t.startsWith('- [ ]')) {
    doc.font('Helvetica').fontSize(bodySize).fillColor('#333');
    doc.text('☐ ' + t.slice(5).trim(), { indent: 12, lineGap: 3 });
    return;
  }
  if (t.startsWith('- ')) {
    doc.font('Helvetica').fontSize(bodySize).fillColor('#333');
    doc.text('• ' + t.slice(2), { indent: 12, lineGap: 3 });
    return;
  }
  if (/^\d+\.\s/.test(t)) {
    doc.font('Helvetica').fontSize(bodySize).fillColor('#333');
    doc.text(t, { indent: 12, lineGap: 3 });
    return;
  }
  if (t.startsWith('**') && t.endsWith('**')) {
    doc.font('Helvetica-Bold').fontSize(bodySize).fillColor('#1a1a1a');
    doc.text(t.replace(/\*\*/g, ''), { lineGap: 3 });
    return;
  }

  let clean = t.replace(/\*\*([^*]+)\*\*/g, '$1').replace(/\*/g, '');
  doc.font(opts.bold ? 'Helvetica-Bold' : 'Helvetica').fontSize(bodySize).fillColor('#333');
  doc.text(clean, { align: 'left', lineGap: 3 });
}

// Cover block
doc.font('Helvetica-Bold').fontSize(22).fillColor('#3d2e24');
doc.text('Casa Dessert', { align: 'center' });
doc.moveDown(0.3);
doc.fontSize(16).fillColor('#1a1a1a');
doc.text('Plan d\'action pour augmenter le CA', { align: 'center' });
doc.moveDown(0.5);
doc.font('Helvetica').fontSize(11).fillColor('#666');
doc.text('Particuliers · Pro · Livreur · Comptable', { align: 'center' });
doc.moveDown(1.2);
doc.fontSize(9).text('Mai 2026 — Document interne', { align: 'center' });
doc.addPage();

for (const line of lines) {
  if (line.startsWith('# Plan d')) continue;
  writeLine(line);
}

doc.end();
