import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const f = path.join(root, 'templates', 'index_base.html');
let html = fs.readFileSync(f, 'utf8');

html = html.replace(
  '<script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js"></script>\n',
  ''
);

html = html.replace(
  '<meta charset="UTF-8">',
  '<meta charset="UTF-8">\n<meta name="csrf-token" content="__CSRF__">'
);

html = html.replace(
  '<div class="catalogue"><div class="catalogue-grid" id="productGrid"></div></div>',
  '<div class="catalogue"><div class="catalogue-grid" id="productGrid">__PRODUCT_GRID__</div></div>'
);

html = html.replace(/mentions-legales\.html/g, 'mentions-legales.php');

const re = /<!-- Firebase \+ App Logic -->\s*<script type="module">[\s\S]*?<\/script>\s*(?=<footer style)/;
if (!re.test(html)) {
  console.error('Firebase block not found');
  process.exit(1);
}
html = html.replace(re, '__APP_SCRIPT__\n\n');

fs.writeFileSync(f, html);
console.log('Patched', f);
