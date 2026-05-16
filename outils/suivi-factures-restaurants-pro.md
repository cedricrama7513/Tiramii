# Suivi factures restaurants pro — Casa Dessert

**Télécharger le fichier Excel :**

- Sur votre PC : dossier [`a-telecharger/suivi-factures-restaurants-pro.xls`](../a-telecharger/suivi-factures-restaurants-pro.xls)
- Sur le site (après déploiement) : [casadessert.fr/outils/](https://casadessert.fr/outils/) ou [lien direct .xls](./suivi-factures-restaurants-pro.xls)

(Ouvrir avec Excel ou LibreOffice Calc.)

## Objectif

Vous saisissez **chaque facture** (montant, restaurant, payé ou non). Dès que :

- **une facture** dépasse **500 €**, ou  
- l’**encours impayé** d’un restaurant (toutes factures non payées) dépasse **500 €**,

une **alerte** s’affiche pour vous rappeler d’**envoyer un message au restaurant** pour le paiement.

## Onglet « Suivi » — colonnes

| Colonne | Contenu |
|--------|---------|
| A | Nom du restaurant |
| B | Date de la facture |
| C | N° facture |
| D | Montant TTC (€) |
| E | Payé ? (`Oui` ou `Non`) |
| F | **Alerte** si facture > 500 € (calculée) |
| G | **Encours impayé** du restaurant (calculé) |
| H | **Alerte** si encours > 500 € → relancer |
| I | Message envoyé ? (`Oui` / `Non`) |
| J | Date de relance (à remplir à la main) |

### Exemples fournis

- **Le Petit Bistrot** : 320 € + 280 € non payés → encours **600 €** → alerte encours.  
- **Brasserie Sud** : une facture à **620 €** → alerte facture + encours.

Supprimez ou modifiez ces lignes d’exemple selon vos vrais clients.

## Onglet « Synthèse »

Liste des restaurants avec l’encours impayé total et une colonne **Alerte > 500 €** (`RELANCER` / `OK`).

Ajoutez les noms de vos restaurants en colonne A (l’onglet Suivi doit contenir les mêmes noms pour que les totaux correspondent).

## Changer le seuil (500 €)

Dans Excel, sur l’onglet **Suivi** :

- Colonne **F** : remplacez `500` dans la formule, par ex. `750`.  
- Colonne **H** : idem sur `G2>500`.  
- Onglet **Synthèse** : colonne **C**, formule `B2>500`.

Formules (Excel français, point-virgule) :

**Alerte facture (colonne F, ligne 2)**  
```excel
=SI(D2>500;"⚠ Facture > 500 € — à relancer";"")
```

**Encours impayé restaurant (colonne G, ligne 2)**  
```excel
=SOMME.SI($D$2:$D$500;$A$2:$A$500;A2)-SOMME.SI.ENS($D$2:$D$500;$A$2:$A$500;A2;$E$2:$E$500;"Oui")
```

**Alerte encours (colonne H, ligne 2)**  
```excel
=SI(G2>500;"⚠ ENVOYER MESSAGE PAIEMENT";"")
```

*(Sur Google Sheets, remplacez les `;` par `,` si votre région l’exige.)*

## Bonnes pratiques

1. **Une ligne = une facture** (ne pas fusionner plusieurs factures sur une ligne).  
2. Mettre **Oui** en colonne E dès que vous avez reçu le virement.  
3. Après envoi du message, noter **Oui** en colonne I et la date en J.  
4. Garder le **même nom** de restaurant partout (ex. pas « Bistrot » puis « Le Petit Bistrot »).

## Lien avec l’admin du site

Les PDF déposés dans **admin.php → Pro → Factures pro** restent l’archive des fichiers.  
Ce tableur sert au **suivi des montants et des relances** (non synchronisé automatiquement avec le site).
