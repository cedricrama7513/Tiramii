import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');

const base =
  'C:/Users/cedri/.cursor/projects/c-Users-cedri-OneDrive-Bureau-tiramii-tiramii-final-ultimate/assets/c__Users_cedri_AppData_Roaming_Cursor_User_workspaceStorage_4ef7ac7b0215f4cf7a93de51166427de_images_';

const map = {
  mms: `${base}IMG_1953-a767da8a-56f4-4f26-86c8-e3318ddcb788.png`,
  kitkat: `${base}composer-annotation-81447a47-2479-42d9-87ed-5c826c561c75.png`,
  raffaello: `${base}IMG_1951-8d5c751b-7dea-4222-9289-0d2100204c3f.png`,
};

function escPhpSingle(s) {
  return s.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

let chunk = '';
for (const [key, p] of Object.entries(map)) {
  if (!fs.existsSync(p)) {
    console.error('Missing:', p);
    process.exit(1);
  }
  const uri = 'data:image/png;base64,' + fs.readFileSync(p).toString('base64');
  chunk += `  '${key}' => '${escPhpSingle(uri)}',\n`;
}

const phpPath = path.join(root, 'includes', 'product_images.php');
let php = fs.readFileSync(phpPath, 'utf8');
if (php.includes("'mms' =>")) {
  console.log('Keys already present, skipping patch');
  process.exit(0);
}
php = php.replace(/\n\];\s*\n?$/, '\n' + chunk + '];\n');
fs.writeFileSync(phpPath, php);
console.log('Patched', phpPath, 'added', Object.keys(map).join(', '));
