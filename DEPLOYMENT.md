# Déploiement Casa Dessert (tiramii.fr) sur Hostinger

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

## 3. Upload des fichiers (FTP / Gestionnaire de fichiers)

1. Dans **FileZilla** ou le **Gestionnaire de fichiers Hostinger**, ouvrez le dossier racine du site (souvent `public_html` ou un sous-dossier du domaine).
2. Uploadez **tout le contenu** du projet :
   - `index.php`, `admin.php`, `mentions-legales.php`, `.htaccess`
   - dossiers `api/`, `assets/`, `config/`, `includes/`, `templates/`, `tools/` (optionnel hors prod)
3. **Ne rendez pas** `config.php` téléchargeable : le `.htaccess` fourni bloque l’accès direct à `config/` et à `config.php` au niveau racine si votre hébergeur l’applique.

Si `config/` est **en dehors** de `public_html` (recommandé sur certains plans), ajustez les chemins dans `index.php` / `api/_bootstrap.php` ou déplacez uniquement `config.php` hors web et adaptez `config/db.php`.

## 3 bis. Diagnostic si la page d’accueil affiche une erreur 500

1. Uploadez `verification-installation.php` à la racine du site (à côté de `index.php`).
2. Ouvrez dans le navigateur : `https://votre-domaine.fr/verification-installation.php`
3. Corrigez tout ce qui est marqué ❌ (souvent : `config.php` absent, tables MySQL non importées, `product_images.php` manquant).
4. **Supprimez** `verification-installation.php` du serveur une fois terminé.

## 4. Vérifier la configuration PHP

- Version PHP **8.0** minimum (hPanel → Paramètres PHP).
- Extensions : **pdo_mysql**, **json**, **mbstring**.

## 5. URLs propres

Le fichier `.htaccess` définit `DirectoryIndex index.php`. Les pages utilisées :

- Boutique : `index.php` (ou `/` si `index.php` est l’index).
- Admin : `admin.php`
- Mentions légales : `mentions-legales.php`
- API : `api/state.php`, `api/sync-reservation.php`, `api/order.php`

Vous pouvez décommenter la redirection **HTTP → HTTPS** dans `.htaccess` une fois le certificat SSL actif.

## 6. Tests après déploiement

1. Ouvrir le site : le catalogue s’affiche avec images et prix.
2. Ajouter un produit au panier : pas d’erreur toast ; vérifier dans les outils développeur (réseau) que `api/sync-reservation.php` répond `{"ok":true}`.
3. Ouvrir `admin.php`, se connecter, modifier un stock (ex. box1 = 5), enregistrer, recharger la boutique : disponibilité cohérente.
4. Passer une commande test avec un code postal en 75, 91, 92, 93 ou 94 (sinon la commande est refusée).
5. Dans phpMyAdmin, vérifier une ligne dans `orders` et `order_items`, et que les quantités dans `stock_levels` ont bien diminué pour les articles commandés.

## Dépannage

- **Page blanche** : activez l’affichage des erreurs temporairement ou consultez les logs PHP (hPanel).
- **Erreur PDO** : vérifiez `DB_HOST` (souvent `localhost` ou un hôte fourni par Hostinger), nom de base, utilisateur, mot de passe.
- **CSRF 403** sur l’API** : cookies de session bloqués ; vérifier même domaine, HTTPS cohérent, pas de navigation privée qui purge la session entre requêtes.
- **Stock qui ne baisse pas après commande** : en admin, une quantité affichée « Illimité » correspond à **999** en base — dans ce cas le site ne déstocke pas. Pour suivre un stock réel, saisissez un nombre inférieur à 999 (ex. 50). Les nouvelles installations importées depuis `database.sql` utilisent des stocks finis par défaut.
