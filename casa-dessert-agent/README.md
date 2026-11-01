# Casa Dessert — assistant vision long terme

Agent **Cursor Agent SDK** pour piloter la **vision et la stratégie** Casa Dessert (produit, B2B, croissance, décisions).

**Ce n’est pas** un agent de maintenance du dépôt Tiramii / casadessert.fr.

## Démarrage

```bash
cd casa-dessert-agent
npm install
npx @grok/agent-sdk login
npx @grok/agent-sdk dev
```

Complétez les fichiers dans le playground sous `vision/`, ou discutez-en avec l’agent.

## Outils

- `vision_recap` — notes + fichiers vision
- `vision_note` — enregistrer décisions / objectifs (mémoire hébergée)

## Déploiement

Voir [Agent SDK deploy](https://cursor.com/docs/bdk) — `npx @grok/agent-sdk deploy --dir .`
