/**
 * Ajoute mms, kitkat, raffaello dans index.html (IMGS + PRODUCTS) pour la version Firebase.
 */
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

const indexPath = path.join(root, 'index.html');
let html = fs.readFileSync(indexPath, 'utf8');

if (html.includes("id:'mms'")) {
  console.log('index.html: flavors already in PRODUCTS, skipping');
  process.exit(0);
}

let imgJs = '';
for (const [key, p] of Object.entries(map)) {
  if (!fs.existsSync(p)) {
    console.error('Missing image:', p);
    process.exit(1);
  }
  const uri = 'data:image/png;base64,' + fs.readFileSync(p).toString('base64');
  imgJs += `  ${key}: '${uri.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}',\n`;
}

const kwMark = "kw:   'data:image";
const kwPos = html.indexOf(kwMark);
if (kwPos === -1) {
  console.error('kw IMGS not found');
  process.exit(1);
}
const afterKw = html.indexOf("',", kwPos);
if (afterKw === -1) {
  console.error('kw IMGS closing not found');
  process.exit(1);
}
const ins = afterKw + 2;
html = html.slice(0, ins) + '\n' + imgJs + html.slice(ins);

const prodLines = `  {id:'mms', name:"Saveur M&M's", price:5, desc:"Mascarpone onctueux, M&M's croquants et sauce chocolat.", badge:'badge-hot', badgeText:'Nouveau', img:'mms'},
  {id:'kitkat', name:'Saveur KitKat', price:5, desc:'Mascarpone, barres KitKat et chocolat au lait.', badge:'badge-new', badgeText:'Nouveau', img:'kitkat'},
  {id:'raffaello', name:'Saveur Raffaello', price:6, desc:'Crème coco-noisette, Raffaello et noix de coco. (+1€ supplément)', badge:'badge-sup', badgeText:'+1€ supplément', img:'raffaello'},
`;

if (!html.includes("{id:'kw',")) {
  console.error('PRODUCTS kw line not found');
  process.exit(1);
}
html = html.replace(
  /(\{id:'kw',[^\n]+\n)(\];)/,
  `$1${prodLines}$2`
);

fs.writeFileSync(indexPath, html);
console.log('Patched index.html IMGS + PRODUCTS');
