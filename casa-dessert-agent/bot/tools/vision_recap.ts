import { defineTool } from "@grok/agent-sdk/tools";
import fs from "node:fs/promises";
import path from "node:path";
import * as z from "zod/v4";

const KV_KEY = "vision:notes";

type VisionNoteRecord = {
  id: string;
  at: string;
  category: string;
  text: string;
};

type VisionNotesState = {
  notes: VisionNoteRecord[];
};

export default defineTool({
  description:
    "Récapitule les notes enregistrées et les fichiers vision/ du workspace Casa Dessert.",
  effect: "read",
  inputSchema: z.object({
    lastNotes: z.number().int().min(1).max(30).default(10),
  }),
  async execute({ lastNotes }, ctx) {
    const raw = await ctx.host.kv.get(KV_KEY);
    const state =
      raw && typeof raw === "object" && !Array.isArray(raw) && "notes" in raw
        ? (raw as VisionNotesState)
        : null;
    const notes = (state?.notes ?? []).slice(-lastNotes);

    const visionDir = path.join(ctx.workspaceDir, "vision");
    let visionFiles: { name: string; preview: string }[] = [];
    try {
      const names = await fs.readdir(visionDir);
      for (const name of names.sort()) {
        if (!name.endsWith(".md")) continue;
        const content = await fs.readFile(path.join(visionDir, name), "utf8");
        const preview = content
          .split("\n")
          .filter((l) => l.trim() && !l.startsWith("#"))
          .slice(0, 3)
          .join(" ")
          .slice(0, 280);
        visionFiles.push({ name, preview: preview || "(à compléter)" });
      }
    } catch {
      visionFiles = [];
    }

    return {
      focus:
        "Assistant vision long terme Casa Dessert — pas de maintenance du site Tiramii sauf demande.",
      recentNotes: notes,
      visionFiles,
    };
  },
});
