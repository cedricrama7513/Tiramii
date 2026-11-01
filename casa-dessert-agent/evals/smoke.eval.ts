import { defineEval } from "@grok/agent-sdk/evals";

export default defineEval({
  tags: ["smoke"],
  description: "L’agent connaît la structure du dépôt Casa Dessert.",
  async test(t) {
    await t.send(
      "Utilise project_map puis dis en une phrase où se trouve le guide de déploiement.",
    );
    t.succeeded();
    t.calledTool("project_map");
  },
});
