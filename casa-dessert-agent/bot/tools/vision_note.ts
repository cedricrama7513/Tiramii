import { defineTool } from "@grok/agent-sdk/tools";
import * as z from "zod/v4";

const Category = z.enum([
  "vision",
  "strategie",
  "produit",
  "commercial",
  "operations",
  "finance",
  "decision",
  "idee",
]);

type VisionNoteRecord = {
  id: string;
  at: string;
  category: z.infer<typeof Category>;
  text: string;
};

type VisionNotesState = {
  notes: VisionNoteRecord[];
};

const KV_KEY = "vision:notes";

export default defineTool({
  description:
    "Enregistre une note durable (décision, objectif, idée) pour la vision long terme Casa Dessert.",
  effect: "write",
  inputSchema: z.object({
    category: Category.describe("Type de note"),
    text: z.string().min(1).max(4000),
  }),
  async execute({ category, text }, ctx) {
    const raw = await ctx.host.kv.get(KV_KEY);
    const state: VisionNotesState =
      raw && typeof raw === "object" && !Array.isArray(raw) && "notes" in raw
        ? (raw as VisionNotesState)
        : { notes: [] };
    const note: VisionNoteRecord = {
      id: `n_${Date.now()}`,
      at: new Date().toISOString(),
      category,
      text: text.trim(),
    };
    state.notes.push(note);
    if (state.notes.length > 200) {
      state.notes = state.notes.slice(-200);
    }
    await ctx.host.kv.put(KV_KEY, state);
    return { saved: true, note };
  },
});
