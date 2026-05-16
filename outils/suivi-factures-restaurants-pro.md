# Suivi factures restaurants pro — Casa Dessert

**Télécharger le fichier Excel :**

- Sur votre PC : dossier [`a-telecharger/suivi-factures-restaurants-pro.xls`](../a-telecharger/suivi-factures-restaurants-pro.xls)
- Sur le site (après déploiement) : [casadessert.fr/outils/](https://casadessert.fr/outils/) ou [lien direct .xls](./suivi-factures-restaurants-pro.xls)

(Ouvrir avec Excel ou LibreOffice Calc.)

## Objectif

**Un onglet par restaurant** : factures mensuelles 2026 + **compte prépayé** (versements de **500 €** déduits facture par facture).

Alertes :

- **Solde crédit ≤ 0 €** → le client doit recharger (**500 €**).
- **Facture > 500 €** ou **encours impayé > 500 €** (suivi classique).

## Compte prépayé (500 €)

1. Le client vous envoie **500 €** → saisissez **500** en colonne **D** (Versement reçu) sur la ligne du mois concerné.
2. Chaque **facture** (colonne **C**) est **déduite** automatiquement du **solde crédit** (colonne **E**).
3. Dès que le **solde ≤ 0** (colonne **F** : alerte), demandez un nouveau versement de **500 €**.

**Solde (ligne 2)** : `= solde d'ouverture + D2 − C2`  
**Solde (lignes suivantes)** : `= solde précédent + versement − facture`

## Onglets restaurants

Clients actuels : **My French factory**, **My french Cantine** (noms identiques à admin → Pro).

| Colonne | Contenu |
|--------|---------|
| A | Date de la facture |
| B | N° facture |
| C | Montant TTC (€) |
| D | **Versement reçu (€)** — mettre **500** à chaque recharge |
| E | **Solde crédit (€)** — calculé |
| F | **Alerte** si solde ≤ 0 € |
| G | Payé ? (`Oui` / `Non`) — hors prépaiement |
| H | Alerte si facture > 500 € |
| I | Encours impayé (calculé) |
| J | Alerte encours > 500 € |
| K | Message envoyé ? |
| L | Date de relance |

## Onglet « Synthèse »

Pour chaque restaurant : **solde crédit actuel**, **alerte solde**, encours impayé, alerte encours.

## Régénérer le fichier

Sur le serveur (avec `config/config.php`) :

```bash
php tools/export-pro-clients-json.php
node tools/build-suivi-factures-xls.mjs
```

Ou admin → **Pro** → **Exporter pour le tableur Excel**, puis `node tools/build-suivi-factures-xls.mjs`.

Dans `tools/pro-clients.json` vous pouvez définir :

- `openingBalance` : crédit déjà en compte au 1er janvier (ex. **500**).
- `depositMonths` : mois où un versement de **500 €** est prérempli en colonne D (ex. `[1, 4]`).

## Formules utiles (Excel français)

**Solde crédit (E2, avec 500 € d’ouverture en config)**  
```excel
=500+D2-C2
```

**Solde crédit (E3)**  
```excel
=E2+D3-C3
```

**Alerte solde (F2)**  
```excel
=SI(E2<=0;"⚠ SOLDE ÉPUISÉ — faire verser 500 €";"")
```

## Lien avec l’admin du site

Les PDF dans **admin.php → Pro → Factures pro** restent l’archive. Ce tableur sert au **suivi des montants, prépaiements et relances**.
