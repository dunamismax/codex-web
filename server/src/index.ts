import path from "node:path";

import { streamCodex } from "./codex";
import {
  childDirectories,
  homeDirectory,
  parentDirectory,
  resolveWorkspaceRoot,
  systemRoot,
  validateWorkspacePath,
} from "./path-utils";
import { env, normalizeOptions, runtimeOptions } from "./runtime";
import { sseHeaders, writeSseEvent } from "./sse";
import { directorySchema, streamSchema } from "./validation";

function corsHeaders() {
  return {
    "Access-Control-Allow-Origin": "*",
    "Access-Control-Allow-Headers": "Content-Type",
    "Access-Control-Allow-Methods": "GET,POST,OPTIONS",
  };
}

function json(data: unknown, status = 200) {
  return new Response(JSON.stringify(data), {
    status,
    headers: {
      "Content-Type": "application/json",
      ...corsHeaders(),
    },
  });
}

function error(message: string, status = 400) {
  return json({ error: message }, status);
}

async function handleDirectory(request: Request) {
  const url = new URL(request.url);
  const parsed = directorySchema.safeParse({ path: url.searchParams.get("path") ?? undefined });

  if (!parsed.success) {
    return error("Directory path is invalid.", 422);
  }

  let current: string;

  try {
    current = resolveWorkspaceRoot(parsed.data.path);
  } catch {
    return error("Directory does not exist.", 422);
  }

  const children = await childDirectories(current);

  return json({
    current,
    parent: parentDirectory(current),
    children,
    system_root: systemRoot(current),
    home: homeDirectory(),
  });
}

async function handleConfig() {
  const workspaceRoot = resolveWorkspaceRoot(undefined);
  const defaultCwd = validateWorkspacePath({
    pathInput: env.CODEX_DEFAULT_CWD,
    workspaceRoot,
    fieldLabel: "cwd",
    defaultPath: workspaceRoot,
    doesNotExistMessage: "Working directory does not exist.",
    outOfBoundsMessage: "Working directory must be inside the selected workspace root.",
  });

  return json({
    defaults: {
      workspaceRoot,
      cwd: defaultCwd,
      model: env.CODEX_DEFAULT_MODEL,
      reasoningEffort: env.CODEX_DEFAULT_REASONING_EFFORT,
      fullAuto: env.CODEX_DEFAULT_FULL_AUTO,
      sandboxMode: env.CODEX_DEFAULT_SANDBOX_MODE,
      approvalPolicy: env.CODEX_DEFAULT_APPROVAL_POLICY,
      webSearch: env.CODEX_DEFAULT_SEARCH,
    },
    options: {
      models: normalizeOptions(runtimeOptions.models),
      reasoningEfforts: normalizeOptions(runtimeOptions.reasoningEfforts),
      sandboxModes: normalizeOptions(runtimeOptions.sandboxModes),
      approvalPolicies: normalizeOptions(runtimeOptions.approvalPolicies),
    },
  });
}

async function handleStream(request: Request) {
  const payload = await request.json().catch(() => null);
  const parsed = streamSchema.safeParse(payload);

  if (!parsed.success) {
    return error("Stream request is invalid.", 422);
  }

  let workspaceRoot: string;
  let cwd: string;
  let addDirs: string[];

  try {
    workspaceRoot = resolveWorkspaceRoot(parsed.data.workspace_root);
    cwd = validateWorkspacePath({
      pathInput: parsed.data.cwd,
      workspaceRoot,
      fieldLabel: "cwd",
      defaultPath: workspaceRoot,
      doesNotExistMessage: "Working directory does not exist.",
      outOfBoundsMessage: "Working directory must be inside the selected workspace root.",
    });

    addDirs = (parsed.data.add_dirs ?? []).map((directory, index) =>
      validateWorkspacePath({
        pathInput: directory,
        workspaceRoot,
        fieldLabel: `add_dirs.${index}`,
        doesNotExistMessage: "Additional writable directory does not exist.",
        outOfBoundsMessage:
          "Additional writable directories must be inside the selected workspace root.",
      }),
    );
  } catch (cause) {
    return error(cause instanceof Error ? cause.message : "Path validation failed.", 422);
  }

  const stream = new TransformStream<string>();
  const writer = stream.writable.getWriter();

  void (async () => {
    try {
      await writeSseEvent(writer, "meta", {
        type: "stream.started",
        session_id: parsed.data.session_id ?? null,
        workspace_root: workspaceRoot,
      });

      await streamCodex({
        prompt: parsed.data.prompt,
        sessionId: parsed.data.session_id,
        model: parsed.data.model ?? env.CODEX_DEFAULT_MODEL,
        reasoningEffort: parsed.data.reasoning_effort ?? env.CODEX_DEFAULT_REASONING_EFFORT,
        fullAuto: parsed.data.full_auto ?? env.CODEX_DEFAULT_FULL_AUTO,
        cwd,
        sandboxMode: parsed.data.sandbox_mode ?? env.CODEX_DEFAULT_SANDBOX_MODE,
        approvalPolicy: parsed.data.approval_policy ?? env.CODEX_DEFAULT_APPROVAL_POLICY,
        webSearch: parsed.data.web_search ?? env.CODEX_DEFAULT_SEARCH,
        additionalDirectories: [...new Set(addDirs)],
        onEvent: async (event) => {
          await writeSseEvent(writer, "codex", event);
        },
        signal: request.signal,
      });
    } catch (cause) {
      await writeSseEvent(writer, "codex", {
        type: "error",
        message: cause instanceof Error ? cause.message : "Codex stream failed unexpectedly.",
      });
    } finally {
      await writeSseEvent(writer, "done", { type: "stream.finished" });
      await writer.close();
    }
  })();

  return new Response(stream.readable, {
    headers: {
      ...sseHeaders(),
      ...corsHeaders(),
    },
  });
}

async function serveStatic(request: Request) {
  const url = new URL(request.url);
  const pathname = url.pathname === "/" ? "/index.html" : url.pathname;
  const filePath = path.join(process.cwd(), "build", "client", pathname);
  const file = Bun.file(filePath);

  if (await file.exists()) {
    return new Response(file);
  }

  return new Response(Bun.file(path.join(process.cwd(), "build", "client", "index.html")));
}

const server = Bun.serve({
  hostname: "0.0.0.0",
  port: env.PORT,
  async fetch(request) {
    const url = new URL(request.url);

    if (request.method === "OPTIONS") {
      return new Response(null, { headers: corsHeaders() });
    }

    try {
      if (url.pathname === "/api/codex/config" && request.method === "GET") {
        return await handleConfig();
      }

      if (url.pathname === "/api/codex/directories" && request.method === "GET") {
        return await handleDirectory(request);
      }

      if (url.pathname === "/api/codex/stream" && request.method === "POST") {
        return await handleStream(request);
      }

      return await serveStatic(request);
    } catch (cause) {
      return error(cause instanceof Error ? cause.message : "Unexpected failure.", 500);
    }
  },
});

console.log(`codex-web server listening on http://${server.hostname}:${server.port}`);
