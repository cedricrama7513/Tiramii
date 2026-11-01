---
name: deploiement-hostinger
description: >-
  Déployer ou diagnostiquer casadessert.fr sur Hostinger (Git, FTP GitHub
  Actions, config.php, health.php). Utiliser quand la prod n’est pas à jour
  ou après un push.
---

# Déploiement Hostinger — Casa Dessert

1. Lire `DEPLOYMENT.md` à la racine du dépôt.
2. Vérifier **https://casadessert.fr/health.php** :
   - `deploy-version` avec un hash Git = déploiement Git OK.
   - `MANQUANT` = Git Hostinger ne met pas à jour `public_html`.
3. `config/config.php` n’est **pas** sur Git : le créer une fois sur le serveur depuis `config/config.example.php`.
4. Si Git Hostinger bloque : workflow `.github/workflows/deploy-hostinger.yml` (secrets FTP dans GitHub).
5. Après déploiement : tester `index.php`, `devis.php`, commande test, admin.

Ne jamais afficher mots de passe, hash admin ou clés FTP depuis les fichiers locaux.
