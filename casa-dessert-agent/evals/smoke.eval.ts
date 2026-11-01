import { defineEval } from "@grok/agent-sdk/evals";

export default defineEval({
  tags: ["smoke"],
  description: "L’agent utilise vision_recap pour le projet long terme.",
  async test(t) {
    await t.send(
      "Appelle vision_recap puis résume en une phrase ton rôle (vision long terme, pas le site Tiramii).",
    );
    t.succeeded();
    t.calledTool("vision_recap");
  },
});
