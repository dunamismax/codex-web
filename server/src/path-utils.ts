import { promises as fs, statSync } from "node:fs";
import os from "node:os";
import path from "node:path";

import { env } from "./runtime";

export function isAbsolutePath(target: string) {
  return path.isAbsolute(target) || /^[a-zA-Z]:[\\/]/.test(target);
}

export function resolveFromRoot(target: string, workspaceRoot: string) {
  if (isAbsolutePath(target)) {
    return path.resolve(target);
  }

  return path.resolve(workspaceRoot, target);
}

export function resolveWorkspaceRoot(input: string | undefined) {
  const target = input?.trim() ? input : env.CODEX_WORKSPACE_ROOT;
  const resolved = isAbsolutePath(target)
    ? path.resolve(target)
    : path.resolve(process.cwd(), target);

  if (!isDirectory(resolved)) {
    throw new Error("Workspace root does not exist.");
  }

  return resolved;
}

export function validateWorkspacePath(params: {
  pathInput: string | undefined;
  workspaceRoot: string;
  fieldLabel: string;
  defaultPath?: string;
  doesNotExistMessage: string;
  outOfBoundsMessage: string;
}) {
  const candidate = params.pathInput?.trim() || params.defaultPath;

  if (!candidate) {
    throw new Error(`${params.fieldLabel}: ${params.doesNotExistMessage}`);
  }

  const resolved = resolveFromRoot(candidate, params.workspaceRoot);
  if (!isDirectory(resolved)) {
    throw new Error(`${params.fieldLabel}: ${params.doesNotExistMessage}`);
  }

  const normalizedRoot = path.resolve(params.workspaceRoot);
  const normalizedResolved = path.resolve(resolved);

  const within =
    normalizedResolved === normalizedRoot ||
    normalizedResolved.startsWith(`${normalizedRoot}${path.sep}`);

  if (!within) {
    throw new Error(`${params.fieldLabel}: ${params.outOfBoundsMessage}`);
  }

  return normalizedResolved;
}

export function parentDirectory(target: string) {
  const parent = path.dirname(target);
  return parent === target ? null : parent;
}

export async function childDirectories(target: string) {
  try {
    const entries = await fs.readdir(target, { withFileTypes: true });
    const children = entries
      .filter((entry) => entry.isDirectory())
      .map((entry) => path.resolve(target, entry.name))
      .slice(0, 250)
      .sort((a, b) => a.localeCompare(b));

    return children;
  } catch {
    return [];
  }
}

export function systemRoot(target: string) {
  if (/^[a-zA-Z]:[\\/]/.test(target)) {
    return `${target.slice(0, 1).toUpperCase()}:\\`;
  }

  return path.parse(target).root || "/";
}

export function homeDirectory() {
  const home = os.homedir();
  return home && isDirectory(home) ? home : null;
}

function isDirectory(target: string) {
  try {
    return statSync(target).isDirectory();
  } catch {
    return false;
  }
}
