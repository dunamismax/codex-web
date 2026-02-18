export type RuntimeOption = { value: string; description: string };

export type RuntimeConfig = {
  defaults: {
    workspaceRoot: string;
    cwd: string;
    model: string;
    reasoningEffort: string;
    fullAuto: boolean;
    sandboxMode: string;
    approvalPolicy: string;
    webSearch: boolean;
  };
  options: {
    models: RuntimeOption[];
    reasoningEfforts: RuntimeOption[];
    sandboxModes: RuntimeOption[];
    approvalPolicies: RuntimeOption[];
  };
};

export type DirectoryListing = {
  current: string;
  parent: string | null;
  children: string[];
  system_root: string;
  home: string | null;
};

export type StreamPayload = {
  prompt: string;
  session_id: string | null;
  workspace_root: string;
  model: string;
  reasoning_effort: string;
  full_auto: boolean;
  cwd: string;
  sandbox_mode: string;
  approval_policy: string;
  web_search: boolean;
  add_dirs: string[];
};

export type CodexEvent = Record<string, unknown> & { type?: string };
