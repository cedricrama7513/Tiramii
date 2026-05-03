<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init_public.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Mentions légales — Casa Dessert';
$lastUpdate = '1er mai 2026';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="index,follow">
  <title><?= h($pageTitle) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --vk: #1a1a1a;
      --vd: #A68966;
      --v: #d4c4b0;
      --bg: #F9F9F7;
      --card: #fff;
      --muted: #333333;
      --line: rgba(166, 137, 102, 0.28);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'Montserrat', system-ui, sans-serif;
      background: var(--bg);
      color: var(--vk);
      line-height: 1.65;
      font-size: 1rem;
    }
    .top {
      position: sticky;
      top: 0;
      z-index: 10;
      background: rgba(243, 234, 252, 0.94);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--line);
      padding: 0.85rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 0.75rem;
    }
    .top a.logo {
      font-family: 'Playfair Display', serif;
      font-size: 1.35rem;
      font-weight: 600;
      color: var(--vk);
      text-decoration: none;
      letter-spacing: 0.04em;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    .brand-logo-img--legal {
      height: 44px;
      width: auto;
      max-width: 44px;
      object-fit: contain;
      display: block;
      filter: drop-shadow(0 2px 6px rgba(26, 26, 26, 0.12));
    }
    .top a.logo:hover { color: var(--vd); }
    .top .back {
      font-size: 0.88rem;
      font-weight: 500;
      color: var(--vd);
      text-decoration: none;
      padding: 0.45rem 1rem;
      border: 1px solid var(--v);
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.6);
      transition: background 0.2s, color 0.2s;
    }
    .top .back:hover { background: var(--vd); color: #fff; border-color: var(--vd); }
    .wrap {
      max-width: 720px;
      margin: 0 auto;
      padding: 2.5rem 1.5rem 4rem;
    }
    h1 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.75rem, 5vw, 2.35rem);
      color: var(--vk);
      margin-bottom: 0.35rem;
      line-height: 1.2;
    }
    .meta {
      font-size: 0.85rem;
      color: var(--muted);
      margin-bottom: 2rem;
      padding-bottom: 1.25rem;
      border-bottom: 1px solid var(--line);
    }
    nav.toc {
      background: var(--card);
      border-radius: 16px;
      padding: 1.15rem 1.25rem;
      margin-bottom: 2rem;
      box-shadow: 0 4px 24px rgba(61, 31, 110, 0.06);
      border: 1px solid var(--line);
    }
    nav.toc p {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--vd);
      margin-bottom: 0.65rem;
      font-weight: 600;
    }
    nav.toc ul { list-style: none; }
    nav.toc li { margin: 0.35rem 0; }
    nav.toc a {
      color: var(--vk);
      text-decoration: none;
      font-size: 0.92rem;
    }
    nav.toc a:hover { color: var(--vd); text-decoration: underline; }
    section {
      margin-bottom: 2.25rem;
    }
    h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1.28rem;
      color: var(--vk);
      margin-bottom: 0.75rem;
      scroll-margin-top: 4.5rem;
    }
    h3 {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--vd);
      margin: 1.15rem 0 0.45rem;
    }
    p, li { margin-bottom: 0.65rem; color: var(--vk); }
    ul { padding-left: 1.2rem; margin-bottom: 0.75rem; }
    li { margin-bottom: 0.4rem; }
    a { color: var(--vd); }
    a:hover { text-decoration: underline; }
    .card {
      background: var(--card);
      border-radius: 14px;
      padding: 1.15rem 1.25rem;
      border: 1px solid var(--line);
      margin-top: 0.5rem;
    }
    .card p:last-child { margin-bottom: 0; }
    footer.legal-foot {
      margin-top: 3rem;
      padding-top: 1.5rem;
      border-top: 1px solid var(--line);
      font-size: 0.88rem;
      color: var(--muted);
    }
  </style>
</head>
<body>
  <header class="top">
    <a class="logo" href="index.php"><?= brand_logo_markup('legal') ?>Casa Dessert</a>
    <a class="back" href="index.php">← Retour à la boutique</a>
  </header>

  <main class="wrap">
    <h1>Mentions légales</h1>
    <p class="meta">Document d’information conforme aux articles 6-III et 19 de la loi n°2004-575 du 21 juin 2004 (LCEN). Dernière mise à jour&nbsp;: <?= h($lastUpdate) ?>.</p>

    <nav class="toc" aria-label="Sommaire">
      <p>Sommaire</p>
      <ul>
        <li><a href="#editeur">1. Éditeur du site</a></li>
        <li><a href="#activite">2. Activité</a></li>
        <li><a href="#hebergement">3. Hébergement</a></li>
        <li><a href="#propriete">4. Propriété intellectuelle</a></li>
        <li><a href="#donnees">5. Données personnelles (RGPD)</a></li>
        <li><a href="#cookies">6. Cookies et traceurs</a></li>
        <li><a href="#responsabilite">7. Responsabilité</a></li>
        <li><a href="#mediation">8. Médiation et litiges</a></li>
        <li><a href="#droit">9. Droit applicable</a></li>
      </ul>
    </nav>

    <section id="editeur">
      <h2>1. Éditeur du site</h2>
      <div class="card">
        <p><strong>Dénomination / nom commercial&nbsp;:</strong> Casa Dessert</p>
        <p><strong>Responsable de la publication&nbsp;:</strong> Cedric Ramahefaharison</p>
        <p><strong>Statut&nbsp;:</strong> Entrepreneur individuel (micro-entrepreneur)</p>
        <p><strong>Adresse du siège&nbsp;:</strong> 60 rue François&nbsp;1<sup>er</sup>, 75008 Paris, France</p>
        <p><strong>Courriel&nbsp;:</strong> <a href="mailto:cedricrama.pro@gmail.com">cedricrama.pro@gmail.com</a></p>
        <p><strong>Téléphone&nbsp;:</strong> <a href="tel:+33664645320">06&nbsp;64&nbsp;64&nbsp;53&nbsp;20</a></p>
        <p><strong>Numéro SIREN&nbsp;:</strong> 944&nbsp;172&nbsp;139</p>
        <p>Le responsable de publication est une personne physique.</p>
      </div>
    </section>

    <section id="activite">
      <h2>2. Activité</h2>
      <p>Le site <strong>tiramii.fr</strong> présente une activité de <strong>vente de desserts artisanaux</strong> (notamment tiramisus) et permet de <strong>passer commande en ligne</strong> avec livraison selon les modalités indiquées sur le site. Les informations tarifaires et pratiques figurant sur les pages du site sont données à titre indicatif et peuvent être ajustées ; seules les conditions validées lors d’une commande font foi.</p>
    </section>

    <section id="hebergement">
      <h2>3. Hébergement</h2>
      <div class="card">
        <p><strong>Hébergeur&nbsp;:</strong> Hostinger International Ltd.</p>
        <p><strong>Adresse&nbsp;:</strong> 61 Lordou Vironos Street, 6023 Larnaca, Chypre</p>
        <p><strong>Site web&nbsp;:</strong> <a href="https://www.hostinger.fr" rel="noopener noreferrer" target="_blank">https://www.hostinger.fr</a></p>
      </div>
      <p>Les données techniques nécessaires au fonctionnement du site sont hébergées sur l’infrastructure de cet hébergeur.</p>
    </section>

    <section id="propriete">
      <h2>4. Propriété intellectuelle</h2>
      <p>L’ensemble des éléments composant le site (structure, textes, photographies, visuels produits, logos, charte graphique, bases de données éventuelles, code, etc.) sont la propriété exclusive de l’éditeur ou font l’objet d’une autorisation d’utilisation, sauf mentions particulières.</p>
      <p>Toute reproduction, représentation, modification, publication ou adaptation, totale ou partielle, des éléments du site, par quelque procédé que ce soit, sans autorisation écrite préalable de l’éditeur, est interdite et pourrait constituer une contrefaçon au sens des articles L.335-2 et suivants du Code de la propriété intellectuelle.</p>
    </section>

    <section id="donnees">
      <h2>5. Données personnelles (RGPD)</h2>
      <p>Dans le cadre de l’utilisation du site et de la gestion des commandes, des données à caractère personnel peuvent être collectées (par exemple&nbsp;: prénom, nom, coordonnées téléphoniques, adresse de livraison, contenu éventuel d’un message ou d’une note de commande, données techniques de connexion).</p>

      <h3>5.1 Responsable du traitement</h3>
      <p>Le responsable du traitement est l’éditeur du site, identifié à la section «&nbsp;Éditeur du site&nbsp;» ci-dessus.</p>

      <h3>5.2 Finalités et bases légales</h3>
      <ul>
        <li><strong>Gestion des commandes, livraisons et facturation éventuelle&nbsp;:</strong> exécution du contrat (article 6.1.b du RGPD).</li>
        <li><strong>Réponses aux demandes de contact&nbsp;:</strong> intérêt légitime ou mesure précontractuelle selon le cas.</li>
        <li><strong>Obligations légales&nbsp;:</strong> conservation comptable ou fiscale le cas échéant (article 6.1.c du RGPD).</li>
        <li><strong>Sécurisation du site et prévention des abus (sessions techniques, journaux)&nbsp;:</strong> intérêt légitime (article 6.1.f du RGPD).</li>
      </ul>

      <h3>5.3 Destinataires</h3>
      <p>Les données sont destinées à l’éditeur et, le cas échéant, à ses sous-traitants strictement nécessaires au fonctionnement du site et de la prestation (hébergeur, prestataire d’envoi de messages ou de paiement si utilisé). Elles ne sont pas revendues à des tiers à des fins commerciales.</p>

      <h3>5.4 Durées de conservation</h3>
      <p>Les données liées aux commandes sont conservées pendant la durée nécessaire à la gestion de la relation commerciale, puis archivées selon les durées légales applicables (notamment obligations comptables). Les données de prospection ou de contact peuvent être conservées pendant une durée raisonnable au regard du contexte, sauf opposition de votre part lorsque le droit s’y prête.</p>

      <h3>5.5 Vos droits</h3>
      <p>Conformément au Règlement (UE) 2016/679 (RGPD) et à la loi «&nbsp;Informatique et Libertés&nbsp;», vous disposez d’un droit d’accès, de rectification, d’effacement, de limitation du traitement, de portabilité (lorsque applicable) et d’opposition pour des motifs tenant à votre situation particulière. Pour exercer ces droits, contactez l’éditeur à l’adresse e-mail indiquée ci-dessus, en joignant si possible une copie d’un titre d’identité.</p>
      <p>Vous pouvez introduire une réclamation auprès de la <a href="https://www.cnil.fr" rel="noopener noreferrer" target="_blank">CNIL</a> (Commission nationale de l’informatique et des libertés), 3 Place de Fontenoy, TSA 80715, 75334 Paris Cedex 07.</p>
    </section>

    <section id="cookies">
      <h2>6. Cookies et traceurs</h2>
      <p>Le site peut utiliser des cookies ou mécanismes équivalents strictement nécessaires à son fonctionnement (par exemple maintien d’une session technique pour le panier et la sécurisation des formulaires). Ces traceurs ne nécessitent pas de consentement préalable au sens de la réglementation lorsqu’ils sont indispensables à la fourniture du service expressément demandé.</p>
      <p>Tout autre traceur à finalité non strictement technique ferait l’objet d’une information et, le cas échéant, d’un recueil de consentement conforme aux recommandations de la CNIL.</p>
    </section>

    <section id="responsabilite">
      <h2>7. Responsabilité</h2>
      <p>L’éditeur s’efforce d’assurer l’exactitude et la mise à jour des informations diffusées sur le site. Toutefois, des erreurs ou omissions peuvent survenir&nbsp;: l’éditeur ne saurait être tenu pour responsable des dommages résultant de l’utilisation du site ou de l’impossibilité momentanée d’y accéder (maintenance, cas de force majeure, dysfonctionnement du réseau ou de l’hébergeur).</p>
      <p>Le site peut contenir des liens vers des sites tiers&nbsp;: l’éditeur n’exerce aucun contrôle sur ces sites et décline toute responsabilité quant à leur contenu ou leurs pratiques.</p>
    </section>

    <section id="mediation">
      <h2>8. Médiation et litiges (consommateurs)</h2>
      <p>Conformément aux articles L.612-1 et suivants du Code de la consommation, si vous êtes un <strong>consommateur</strong> et qu’un litige n’a pas pu être résolu directement avec l’éditeur, vous avez la possibilité de recourir gratuitement à un <strong>médiateur de la consommation</strong> en vue d’une résolution amiable. Vous pouvez également utiliser la plateforme européenne de <a href="https://ec.europa.eu/consumers/odr" rel="noopener noreferrer" target="_blank">règlement en ligne des litiges (ODR)</a>.</p>
      <p>L’éditeur peut préciser ultérieurement le nom et les coordonnées d’un médiateur agréé auquel il adhère&nbsp;; à défaut, vous pouvez consulter la liste des médiateurs sur le site du <a href="https://www.economie.gouv.fr/mediation-conso" rel="noopener noreferrer" target="_blank">ministère chargé de l’économie</a>.</p>
    </section>

    <section id="droit">
      <h2>9. Droit applicable</h2>
      <p>Les présentes mentions légales sont régies par le <strong>droit français</strong>. En cas de litige, et sous réserve des règles d’ordre public applicables aux consommateurs, les tribunaux français seront seuls compétents.</p>
    </section>

    <footer class="legal-foot">
      <p>Pour toute question relative à ce document ou au traitement de vos données, écrivez à <a href="mailto:cedricrama.pro@gmail.com">cedricrama.pro@gmail.com</a>.</p>
    </footer>
  </main>
</body>
</html>
