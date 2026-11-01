import path from "node:path";
import { fileURLToPath } from "node:url";

/** Racine du dépôt Casa Dessert (parent du dossier de l’agent). */
export const REPO_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../../..",
);

export function resolveRepoPath(relativePath: string): string | null {
  const trimmed = relativePath.trim().replace(/^[/\\]+/, "");
  const abs = path.resolve(REPO_ROOT, trimmed);
  const rel = path.relative(REPO_ROOT, abs);
  if (rel.startsWith("..") || path.isAbsolute(rel)) {
    return null;
  }
  return abs;
}
