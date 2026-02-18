import { z } from "zod";

const envSchema = z.object({
  NODE_ENV: z.enum(["development", "test", "production"]).default("development"),
  PORT: z.coerce.number().int().positive().default(8787),
  CODEX_BINARY: z.string().default("codex"),
  CODEX_WORKSPACE_ROOT: z.string().default(process.cwd()),
  CODEX_DEFAULT_CWD: z.string().default(process.cwd()),
  CODEX_DEFAULT_MODEL: z.string().default("gpt-5.3-codex"),
  CODEX_DEFAULT_REASONING_EFFORT: z.string().default("high"),
  CODEX_DEFAULT_FULL_AUTO: z.coerce.boolean().default(false),
  CODEX_DEFAULT_SANDBOX_MODE: z.string().default("workspace-write"),
  CODEX_DEFAULT_APPROVAL_POLICY: z.string().default("on-request"),
  CODEX_DEFAULT_SEARCH: z.coerce.boolean().default(false),
  CODEX_SKIP_GIT_REPO_CHECK: z.coerce.boolean().default(true),
  CODEX_PROCESS_TIMEOUT: z.coerce.number().int().positive().default(1800),
  DATABASE_URL: z.string().optional(),
});

export const runtimeOptions = {
  models: {
    "gpt-5.3-codex": "Latest frontier agentic coding model.",
    "gpt-5.2-codex": "Frontier agentic coding model.",
    "gpt-5.1-codex-max": "Codex-optimized flagship for deep and fast reasoning.",
    "gpt-5.2": "Latest frontier model with improvements across knowledge, reasoning and coding.",
    "gpt-5.1-codex-mini": "Optimized for codex. Cheaper, faster, but less capable.",
  },
  reasoningEfforts: {
    low: "Fast responses with lighter reasoning.",
    medium: "Balances speed and reasoning depth for everyday tasks.",
    high: "Greater reasoning depth for complex problems.",
    xhigh: "Extra high reasoning depth for complex problems.",
  },
  sandboxModes: {
    "read-only": "Read files without writing.",
    "workspace-write": "Write changes in the workspace.",
    "danger-full-access": "No sandbox restrictions (use with caution).",
  },
  approvalPolicies: {
    untrusted: "Only trusted commands run without approval.",
    "on-failure": "Ask for approval only if a command fails.",
    "on-request": "Model asks for approval when needed.",
    never: "Never ask for approval.",
  },
} as const;

export const env = envSchema.parse(process.env);

export function normalizeOptions(options: Record<string, string>) {
  return Object.entries(options).map(([value, description]) => ({ value, description }));
}
