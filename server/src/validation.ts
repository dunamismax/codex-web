import { z } from "zod";

import { runtimeOptions } from "./runtime";

export const streamSchema = z.object({
  prompt: z.string().trim().min(1).max(120000),
  session_id: z.string().max(255).nullable().optional(),
  workspace_root: z.string().max(4096).optional(),
  model: z.enum(Object.keys(runtimeOptions.models) as [string, ...string[]]).optional(),
  reasoning_effort: z
    .enum(Object.keys(runtimeOptions.reasoningEfforts) as [string, ...string[]])
    .optional(),
  full_auto: z.boolean().optional(),
  cwd: z.string().max(4096).optional(),
  sandbox_mode: z
    .enum(Object.keys(runtimeOptions.sandboxModes) as [string, ...string[]])
    .optional(),
  approval_policy: z
    .enum(Object.keys(runtimeOptions.approvalPolicies) as [string, ...string[]])
    .optional(),
  web_search: z.boolean().optional(),
  add_dirs: z.array(z.string().max(4096)).max(8).optional(),
});

export const directorySchema = z.object({
  path: z.string().max(4096).optional(),
});

export type StreamRequest = z.infer<typeof streamSchema>;
