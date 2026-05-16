# Suivi factures restaurants pro — Casa Dessert

**Télécharger le fichier Excel :**

- Sur votre PC : dossier [`a-telecharger/suivi-factures-restaurants-pro.xls`](../a-telecharger/suivi-factures-restaurants-pro.xls)
- Sur le site (après déploiement) : [casadessert.fr/outils/](https://casadessert.fr/outils/) ou [lien direct .xls](./suivi-factures-restaurants-pro.xls)

(Ouvrir avec Excel ou LibreOffice Calc.)

## Objectif

**Un onglet par restaurant** : les factures de l’année sont déjà préremplies (date, numéro, montant). Vous ajustez surtout les montants et le statut « Payé ? ».

Dès que :

- **une facture** dépasse **500 €**, ou  
- l’**encours impayé** du restaurant dépasse **500 €**,

une **alerte** s’affiche pour vous rappeler d’**envoyer un message** pour le paiement.

## Onglets restaurants (ex. « Le Petit Bistrot », « Salon Thé Marais »)

| Colonne | Contenu |
|--------|---------|
| A | Date de la facture |
| B | N° facture |
| C | Montant TTC (€) |
| D | Payé ? (`Oui` ou `Non`) |
| E | **Alerte** si facture > 500 € (calculée) |
| F | **Encours impayé** (calculé) |
| G | **Alerte** si encours > 500 € |
| H | Message envoyé ? (`Oui` / `Non`) |
| I | Date de relance |

### Factures préremplies

- **12 lignes par restaurant** : une facture par mois en 2026 (du 1er janvier au 1er décembre).
- Numéros du type `FAC-BIST-2026-01`, `FAC-STM-2026-01`, etc.
- Montants mensuels déjà renseignés ; modifiez la colonne C si besoin.
- Les mois déjà réglés sont en « Payé ? = Oui » (exemples) ; le reste est en « Non ».

## Onglet « Synthèse »

Les deux restaurants avec l’encours impayé total et une colonne **Alerte > 500 €** (`RELANCER` / `OK`).

## Changer les noms ou montants par défaut

Éditez `tools/build-suivi-factures-xls.mjs` (tableau `restaurants`), puis :

```bash
node tools/build-suivi-factures-xls.mjs
```

## Changer le seuil (500 €)

Sur chaque onglet restaurant :

- Colonne **E** : remplacez `500` dans la formule.  
- Colonne **G** : idem sur `F2>500`.  
- Onglet **Synthèse** : colonne **C**, formule `B2>500`.

**Encours impayé (colonne F, ligne 2)**  
```excel
=SOMME($C$2:$C$120)-SOMME.SI($C$2:$C$120;$D$2:$D$120;"Oui")
```

## Bonnes pratiques

1. **Une ligne = une facture**.  
2. Mettre **Oui** en colonne D dès réception du virement.  
3. Après relance, noter **Oui** en colonne H et la date en I.  
4. Pour renommer un restaurant : onglet + `build-suivi-factures-xls.mjs` puis régénération.

## Lien avec l’admin du site

Les PDF dans **admin.php → Pro → Factures pro** restent l’archive. Ce tableur sert au **suivi des montants et relances** (non synchronisé avec le site).
