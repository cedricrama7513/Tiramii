# Déploiement Casa Dessert (casadessert.fr) sur Hostinger

Ce guide suppose un hébergement **Hostinger** avec **PHP 8+** et **MySQL** (phpMyAdmin).

## 1. Préparer les fichiers en local

1. Copiez `config/config.example.php` vers `config/config.php`.
2. Renseignez les accès MySQL (hPanel → Bases de données MySQL).
3. Générez un hash pour le mot de passe admin :

```bash
php -r "echo password_hash('VotreMotDePasseSecret', PASSWORD_DEFAULT), PHP_EOL;"
```

Collez la chaîne dans `config.php` sous la clé `admin_password_hash`.

4. Vérifiez que `includes/product_images.php` est présent (généré par `node tools/extract-imgs.mjs` à partir de `index.html` si besoin).

## 2. Importer la base de données

1. Connectez-vous à **phpMyAdmin** (hPanel).
2. Sélectionnez la base créée pour le site (ou créez-en une).
3. Onglet **Importer** → choisissez `database.sql` → Exécuter.
4. Vérifiez que les tables `products`, `stock_levels`, `orders`, etc. existent.

Vous pouvez supprimer les lignes d’exemple `INSERT INTO orders` / `order_items` dans `database.sql` avant import si vous voulez une base vide.

## 3. Déploiement Git Hostinger (recommandé)

Dépôt GitHub : `https://github.com/cedricrama7513/Tiramii` — branche **`main`**.

### Configurer une fois

1. **hPanel** → **Sites** → **casadessert.fr** → **Git** (ou **Avancé** → **Git**).
2. Connectez le dépôt GitHub `cedricrama7513/Tiramii`, branche **`main`**.
3. **Répertoire d’installation** : laissez **vide** ou mettez **`public_html`** / **`.`**  
   **Ne pas** mettre un sous-dossier (`Tiramii`, `public_html/Tiramii`, etc.) : le site doit être directement dans  
   `/home/…/domains/casadessert.fr/public_html/`.
4. Cliquez sur **Déployer** (Deploy).
5. Activez **Déploiement automatique** : copiez l’URL webhook Hostinger → **GitHub** → dépôt **Tiramii** → **Settings** → **Webhooks** → **Add webhook** → collez l’URL, événement **push**.

`config/config.php` n’est **pas** sur Git (`.gitignore`) : créez-le une fois à la main sur le serveur ; il ne sera pas écrasé aux déploiements.

### Vérifier que Git fonctionne

Après chaque `git push`, ouvrez : **https://casadessert.fr/health.php**

| Indicateur | Git OK | Git ne déploie pas |
|------------|--------|---------------------|
| `deploy-version=…` | Une ligne avec un hash (ex. `daf9500`) | `deploy-version=MANQUANT` |
| `devis.php` | `OK devis.php` | `MANQUANT devis.php` |
| `index.php` | environ **7900+ octets** | environ **7018 octets** (ancienne version) |

Si Git ne met pas à jour le site : cliquez **Déployer** dans hPanel, vérifiez le chemin d’installation, puis le webhook GitHub. En dernier recours, uploadez **`devis.php`** à la main dans `public_html` (voir section 4).

### Déploiement automatique GitHub Actions (recommandé si Git Hostinger bloque)

Le workflow `.github/workflows/deploy-hostinger.yml` envoie les fichiers par **FTP** à chaque `git push` sur `main`.

1. **hPanel** → **Fichiers** → **Comptes FTP** : notez hôte, utilisateur, mot de passe.
2. **GitHub** → dépôt **Tiramii** → **Settings** → **Secrets and variables** → **Actions** → **New repository secret** :
   - `FTP_HOST` — ex. `ftp.casadessert.fr` ou l’hôte indiqué par Hostinger
   - `FTP_USER` — utilisateur FTP
   - `FTP_PASS` — mot de passe FTP
   - `FTP_REMOTE_DIR` (optionnel) — chemin distant, souvent `./` ou `/domains/casadessert.fr/public_html/`
3. **Actions** → workflow **Deploy to Hostinger** → **Run workflow** (ou poussez un commit sur `main`).
4. Vérifiez https://casadessert.fr/health.php (`deploy-version` + `OK devis.php`).

`config/config.php` sur le serveur **n’est pas écrasé** (exclu du déploiement).

## 4. Upload des fichiers (FTP / Gestionnaire de fichiers)

1. Dans **FileZilla** ou le **Gestionnaire de fichiers Hostinger**, ouvrez le dossier racine du site (souvent `public_html` ou un sous-dossier du domaine).
2. Uploadez **tout le contenu** du projet :
   - `index.php`, `admin.php`, `mentions-legales.php`, **`pro.php`** (devis pro), `pro-login.php`, `pro-register.php`, `pro-boutique.php`, `.htaccess`
   - dossiers `api/` (dont **`api/pro-quote.php`**), `assets/`, `config/`, `includes/` (dont `pro_quote_notify.php`, `pro_b2b.php`, `ensure_pro_prices.php`), `templates/`, `tools/` (optionnel hors prod)
3. **Ne rendez pas** `config.php` téléchargeable : le `.htaccess` fourni bloque l’accès direct à `config/` et à `config.php` au niveau racine si votre hébergeur l’applique.

Si `config/` est **en dehors** de `public_html` (recommandé sur certains plans), ajustez les chemins dans `index.php` / `api/_bootstrap.php` ou déplacez uniquement `config.php` hors web et adaptez `config/db.php`.

## 4 bis. Diagnostic si la page d’accueil affiche une erreur 500

1. Uploadez `verification-installation.php` à la racine du site (à côté de `index.php`).
2. Ouvrez dans le navigateur : `https://casadessert.fr/verification-installation.php`
3. Corrigez tout ce qui est marqué ❌ (souvent : `config.php` absent, tables MySQL non importées, `product_images.php` manquant).
4. **Supprimez** `verification-installation.php` du serveur une fois terminé.

## 5. Vérifier la configuration PHP

- Version PHP **8.0** minimum (hPanel → Paramètres PHP).
- Extensions : **pdo_mysql**, **json**, **mbstring**.

## 6. URLs propres

Le fichier `.htaccess` définit `DirectoryIndex index.php`. Les pages utilisées :

- Boutique : `index.php` (ou `/` si `index.php` est l’index).
- **Devis professionnels** : `https://casadessert.fr/devis` ou `index.php?page=devis` (évite le 404 si `pro.php` n’est pas encore uploadé). Ancienne URL : `pro.php?tab=devis`.
- Admin : `admin.php` (demandes reçues : onglet **Pro** → section « Demandes de devis »).
- Mentions légales : `mentions-legales.php`
- API : `api/state.php`, `api/sync-reservation.php`, `api/order.php`, **`api/pro-quote.php`**

Vous pouvez décommenter la redirection **HTTP → HTTPS** dans `.htaccess` une fois le certificat SSL actif.

## 7. Tests après déploiement

1. Ouvrir le site : le catalogue s’affiche avec images et prix.
2. Ajouter un produit au panier : pas d’erreur toast ; vérifier dans les outils développeur (réseau) que `api/sync-reservation.php` répond `{"ok":true}`.
3. Ouvrir `admin.php`, se connecter, modifier un stock (ex. box1 = 5), enregistrer, recharger la boutique : disponibilité cohérente.
4. Passer une commande test avec un code postal en 75, 91, 92, 93 ou 94 (sinon la commande est refusée).
5. Dans phpMyAdmin, vérifier une ligne dans `orders` et `order_items`, et que les quantités dans `stock_levels` ont bien diminué pour les articles commandés.
6. **Devis pro** : ouvrir `https://casadessert.fr/devis` (ou `index.php?page=devis`), ajouter une ligne produit, envoyer un test. Vérifier dans `admin.php?tab=pro` qu’une ligne apparaît dans « Demandes de devis ». Dans **admin.php** (onglet Particulier), renseigner les **Prix pro (€)** pour que l’estimation HT s’affiche (champ vide = « Sur devis » pour ce produit).

## Dépannage

- **Page blanche** : activez l’affichage des erreurs temporairement ou consultez les logs PHP (hPanel).
- **Erreur PDO** : vérifiez `DB_HOST` (souvent `localhost` ou un hôte fourni par Hostinger), nom de base, utilisateur, mot de passe.
- **CSRF 403** sur l’API** : cookies de session bloqués ; vérifier même domaine, HTTPS cohérent, pas de navigation privée qui purge la session entre requêtes.
- **Stock qui ne baisse pas après commande** : en admin, une quantité affichée « Illimité » correspond à **999** en base — dans ce cas le site ne déstocke pas. Pour suivre un stock réel, saisissez un nombre inférieur à 999 (ex. 50). Les nouvelles installations importées depuis `database.sql` utilisent des stocks finis par défaut.
- **Git ne déploie pas** : voir section 3 — `health.php` doit afficher `deploy-version=…` et `OK devis.php`. Sinon **Déployer** dans hPanel ou upload manuel de `devis.php`.
- **Page devis introuvable (404)** : uploadez **`devis.php`** à la racine `public_html` (fichier autonome, un seul upload suffit).
- **« Enregistrement impossible »** à l’envoi du devis : la table `pro_quote_requests` manque — ouvrez `pro.php` une fois (création auto) ou importez `database_pro_b2b.sql` ; vérifiez les droits MySQL `CREATE TABLE`.
- **« Jeton CSRF invalide »** sur le devis : rechargez la page, même domaine HTTP/HTTPS, cookies autorisés.
- **Devis reçu en admin mais pas d’e-mail** : renseignez `notify.owner_email` et SMTP dans `config/config.php` (comme pour les commandes).
