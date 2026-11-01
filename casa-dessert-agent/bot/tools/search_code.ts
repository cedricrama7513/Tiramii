import { defineTool } from "@grok/agent-sdk/tools";
import { execFile } from "node:child_process";
import { promisify } from "node:util";
import * as z from "zod/v4";
import { REPO_ROOT } from "../lib/repo.js";

const execFileAsync = promisify(execFile);

export default defineTool({
  description:
    "Recherche du texte ou d’une expression régulière dans le code du site Casa Dessert (ripgrep).",
  effect: "read",
  inputSchema: z.object({
    pattern: z.string().min(1).describe("Mot-clé ou regex ripgrep"),
    glob: z
      .string()
      .optional()
      .describe("Filtre glob optionnel, ex. *.php ou assets/**/*.js"),
    maxLines: z.number().int().min(1).max(200).default(80),
  }),
  async execute({ pattern, glob, maxLines }) {
    const args = [
      "--line-number",
      "--no-heading",
      "--max-count",
      String(maxLines),
      pattern,
    ];
    if (glob) {
      args.unshift("--glob", glob);
    }
    args.push(".");

    try {
      const { stdout } = await execFileAsync("rg", args, {
        cwd: REPO_ROOT,
        maxBuffer: 2 * 1024 * 1024,
        timeout: 30_000,
      });
      const lines = stdout.trim().split("\n").filter(Boolean);
      return {
        matchCount: lines.length,
        truncated: lines.length >= maxLines,
        lines,
      };
    } catch (err: unknown) {
      const e = err as { code?: number; stdout?: string; stderr?: string };
      if (e.code === 1) {
        return { matchCount: 0, truncated: false, lines: [] as string[] };
      }
      return {
        matchCount: 0,
        truncated: false,
        lines: [] as string[],
        error: e.stderr?.trim() || "Recherche impossible.",
      };
    }
  },
});
