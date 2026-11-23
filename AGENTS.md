# Casa Dessert — guide pour les agents

Site de vente de tiramisus et desserts (casadessert.fr) : boutique particuliers, espace professionnels (B2B), admin, outils Excel.
Tout le contenu (code, commentaires, messages, commits) est en **français**.

## Stack

- PHP 8 sans framework, `declare(strict_types=1);` en tête de chaque fichier, fonctions préfixées `tiramii_`.
- MySQL via PDO (`config/db.php`), requêtes **toujours préparées**.
- Hébergement Hostinger. Déploiement automatique : chaque push sur `main` part en FTP/SFTP (`.github/workflows/deploy-hostinger.yml`). **Un merge sur `main` = mise en production.**
- `config/config.php` (secrets, hash admin, SMTP, EmailJS) n'est pas versionné ; le modèle est `config/config.example.php`.
- Pas de migrations : les tables et colonnes sont créées à la volée par des fonctions `tiramii_ensure_*` (`CREATE TABLE IF NOT EXISTS`, `SHOW COLUMNS` puis `ALTER TABLE`), entourées de `try/catch (Throwable)` car les droits MySQL peuvent manquer.

## Conventions

- Échapper toute sortie HTML avec `h()` (`includes/functions.php`).
- Tout POST (formulaire ou API JSON) vérifie le CSRF : `csrf_verify(csrf_token_from_request())`. Les API lisent l'en-tête `X-CSRF-Token`.
- Les API JSON passent par `api/_bootstrap.php` et répondent avec `tiramii_json_response(['ok' => ..., 'error' => ...], $code)`.
- Actions admin : vérifier `$_SESSION['admin_ok']`, puis pattern POST → flash `$_SESSION['admin_flash_success']` → redirection (PRG).
- Fuseau horaire métier : `Europe/Paris`.
- Commits courts au format `feat(zone): ...`, `fix(zone): ...`, `docs: ...`.

## Vérifier son travail

Aucun test automatisé. Au minimum :

```bash
php -l chemin/du/fichier.php
```

Pour tester en local : PHP 8 + MySQL, importer `database.sql` puis `database_pro_b2b.sql`, copier `config/config.example.php` en `config/config.php`, puis `php -S localhost:8000`. Après déploiement, `https://casadessert.fr/health.php` indique la version déployée.

## Agents spécialisés

Trois agents sont définis dans `.cursor/agents/`, chacun avec une règle associée dans `.cursor/rules/` :

| Agent | Domaine |
|-------|---------|
| `admin-commandes` | `admin.php`, commandes particuliers, stock, livraison, onglet Livreur, notifications |
| `pro-b2b` | Comptes pro, boutique pro, commandes pro, devis, CA et factures pro |
| `outils-excel` | Tableurs et PDF de `outils/` et `a-telecharger/`, scripts de génération dans `tools/` |

Hors de ces domaines (site vitrine, SEO, prospection), suivre les conventions ci-dessus.
