import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const L = fs.readFileSync(path.join(root, 'index.html'), 'utf8').split(/\r?\n/);
let out = "<?php\n/** Images produit (data URI — inchangé visuellement). */\nreturn [\n";
for (let i = 348; i <= 353; i++) {
  const line = L[i];
  const m = line.match(/^\s*(\w+):\s*'(data:image[^']+)'/);
  if (m) {
    const escaped = m[2].replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    out += `  '${m[1]}' => '${escaped}',\n`;
  }
}
out += "];\n";
const target = path.join(root, 'includes', 'product_images.php');
fs.mkdirSync(path.dirname(target), { recursive: true });
fs.writeFileSync(target, out);
console.log('Wrote', target, 'bytes', out.length);
