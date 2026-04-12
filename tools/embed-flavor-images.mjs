/**
 * Embarque les PNG KitKat / M&M's / Raffaello dans product_images.php et index.html.
 * Remplace les entrées existantes si présentes (mise à jour visuels).
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

const base =
  'C:/Users/cedri/.cursor/projects/c-Users-cedri-OneDrive-Bureau-tiramii-tiramii-final-ultimate/assets/c__Users_cedri_AppData_Roaming_Cursor_User_workspaceStorage_4ef7ac7b0215f4cf7a93de51166427de_images_';

/** 1 = KitKat, 2 = M&M's, 3 = Raffaello */
const map = {
  kitkat: `${base}composer-annotation-24cc88e9-c581-4d87-9068-dace4a2c8538.png`,
  mms: `${base}composer-annotation-100b2f8f-971f-4eb9-bc99-ea2c72baa6ec.png`,
  raffaello: `${base}EC2C030F-72BE-4293-85DA-1C3970A983C4-05575983-40ec-454a-a219-c71c68cca68b.png`,
};

function escPhpSingle(s) {
  return s.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

const FLAVOR_ORDER = ['mms', 'kitkat', 'raffaello'];

function buildChunkPhp() {
  let chunk = '';
  for (const key of FLAVOR_ORDER) {
    const p = map[key];
    if (!fs.existsSync(p)) {
      console.error('Missing:', p);
      process.exit(1);
    }
    const uri = 'data:image/png;base64,' + fs.readFileSync(p).toString('base64');
    chunk += `  '${key}' => '${escPhpSingle(uri)}',\n`;
  }
  return chunk;
}

function buildChunkJs() {
  let chunk = '';
  for (const key of FLAVOR_ORDER) {
    const p = map[key];
    const uri = 'data:image/png;base64,' + fs.readFileSync(p).toString('base64');
    chunk += `  ${key}: '${uri.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}',\n`;
  }
  return chunk;
}

const phpPath = path.join(root, 'includes', 'product_images.php');
let php = fs.readFileSync(phpPath, 'utf8');
for (const key of Object.keys(map)) {
  php = php.replace(new RegExp(`  '${key}' => 'data:image[^']+',\\r?\\n`, 'g'), '');
}
if (!php.includes("'kw' =>")) {
  console.error('product_images.php: unexpected structure');
  process.exit(1);
}
php = php.replace(/\n\];\s*\n?$/, '\n' + buildChunkPhp() + '];\n');
fs.writeFileSync(phpPath, php);
console.log('Updated', phpPath);

const indexPath = path.join(root, 'index.html');
if (fs.existsSync(indexPath)) {
  let html = fs.readFileSync(indexPath, 'utf8');
  if (html.includes("kw:   'data:image")) {
    for (const key of Object.keys(map)) {
      html = html.replace(new RegExp(`  ${key}: 'data:image[^']+',\\r?\\n`, 'g'), '');
    }
    const kwMark = "kw:   'data:image";
    const kwPos = html.indexOf(kwMark);
    if (kwPos !== -1) {
      const ins = html.indexOf("',", kwPos) + 2;
      html = html.slice(0, ins) + '\n' + buildChunkJs() + html.slice(ins);
      fs.writeFileSync(indexPath, html);
      console.log('Updated', indexPath, 'IMGS');
    }
  }
}

console.log('Done:', Object.keys(map).join(', '));
