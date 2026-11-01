import { defineTool } from "@grok/agent-sdk/tools";
import fs from "node:fs/promises";
import path from "node:path";
import * as z from "zod/v4";
import { REPO_ROOT } from "../lib/repo.js";

const KEY_PATHS = [
  "index.php",
  "index.html",
  "admin.php",
  "pro-boutique.php",
  "pro-login.php",
  "config/config.example.php",
  "database.sql",
  "DEPLOYMENT.md",
  "README.md",
  "api/order.php",
  "includes/functions.php",
  "assets/js/shop.js",
  ".github/workflows/deploy-hostinger.yml",
] as const;

export default defineTool({
  description:
    "Vue d’ensemble du dépôt Casa Dessert : chemins importants, présence des fichiers clés, déploiement.",
  effect: "read",
  inputSchema: z.object({}),
  async execute() {
    const entries: { path: string; exists: boolean; bytes?: number }[] = [];
    for (const rel of KEY_PATHS) {
      const abs = path.join(REPO_ROOT, rel);
      try {
        const st = await fs.stat(abs);
        entries.push({
          path: rel,
          exists: true,
          bytes: st.isFile() ? st.size : undefined,
        });
      } catch {
        entries.push({ path: rel, exists: false });
      }
    }

    let topLevel: string[] = [];
    try {
      topLevel = (await fs.readdir(REPO_ROOT))
        .filter((n) => !n.startsWith("."))
        .sort();
    } catch {
      topLevel = [];
    }

    return {
      repoRoot: REPO_ROOT,
      site: "casadessert.fr",
      stack: "PHP 8+, MySQL, JS boutique, déploiement Hostinger / GitHub Actions",
      topLevel,
      keyFiles: entries,
      hints: [
        "config/config.php est local (gitignore) — voir config/config.example.php",
        "Admin : admin.php · Pro B2B : pro-boutique.php, api/pro_*.php",
        "Guide déploiement : DEPLOYMENT.md · Santé prod : health.php",
      ],
    };
  },
});
