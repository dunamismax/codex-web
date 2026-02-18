import { env } from "./runtime";

type StreamEvent = Record<string, unknown>;

type CodexParams = {
  prompt: string;
  sessionId?: string | null;
  model?: string;
  reasoningEffort?: string;
  fullAuto: boolean;
  cwd?: string;
  sandboxMode?: string;
  approvalPolicy?: string;
  webSearch: boolean;
  additionalDirectories: string[];
  onEvent: (event: StreamEvent) => Promise<void>;
  signal?: AbortSignal;
};

function parseLine(line: string, stream: "stdout" | "stderr") {
  const trimmed = line.trim();
  if (!trimmed) {
    return null;
  }

  try {
    const parsed = JSON.parse(trimmed) as StreamEvent;
    if (parsed && typeof parsed === "object" && typeof parsed.type === "string") {
      return { ...parsed, _stream: stream };
    }
  } catch {
    return {
      type: "cli.raw",
      stream,
      message: trimmed,
    } satisfies StreamEvent;
  }

  return {
    type: "cli.raw",
    stream,
    message: trimmed,
  } satisfies StreamEvent;
}

function buildCommand(params: CodexParams) {
  const isResuming = Boolean(params.sessionId);
  const cmd = [env.CODEX_BINARY];

  if (!params.fullAuto && params.approvalPolicy) {
    cmd.push("--ask-for-approval", params.approvalPolicy);
  }

  if (params.webSearch) {
    cmd.push("--search");
  }

  cmd.push("exec");

  if (isResuming) {
    cmd.push("resume");
  }

  cmd.push("--json");

  if (env.CODEX_SKIP_GIT_REPO_CHECK) {
    cmd.push("--skip-git-repo-check");
  }

  if (params.fullAuto) {
    cmd.push("--full-auto");
  } else if (!isResuming && params.sandboxMode) {
    cmd.push("--sandbox", params.sandboxMode);
  }

  if (params.model) {
    cmd.push("--model", params.model);
  }

  if (params.reasoningEffort) {
    cmd.push("--config", `model_reasoning_effort="${params.reasoningEffort}"`);
  }

  if (!isResuming && params.cwd) {
    cmd.push("--cd", params.cwd);
  }

  if (!isResuming) {
    for (const directory of params.additionalDirectories) {
      cmd.push("--add-dir", directory);
    }
  }

  if (isResuming && params.sessionId) {
    cmd.push(params.sessionId);
  }

  cmd.push("-");

  return cmd;
}

async function forwardLines(
  stream: ReadableStream<Uint8Array> | null,
  type: "stdout" | "stderr",
  onEvent: (event: StreamEvent) => Promise<void>,
) {
  if (!stream) {
    return;
  }

  const reader = stream.getReader();
  const decoder = new TextDecoder();
  let buffer = "";

  while (true) {
    const { done, value } = await reader.read();
    if (done) {
      break;
    }

    buffer += decoder.decode(value, { stream: true });

    while (true) {
      const index = buffer.indexOf("\n");
      if (index === -1) {
        break;
      }

      const line = buffer.slice(0, index);
      buffer = buffer.slice(index + 1);
      const event = parseLine(line, type);

      if (event) {
        await onEvent(event);
      }
    }
  }

  buffer += decoder.decode();

  const tail = parseLine(buffer, type);
  if (tail) {
    await onEvent(tail);
  }
}

export async function streamCodex(params: CodexParams) {
  const process = Bun.spawn(buildCommand(params), {
    cwd: params.cwd || env.CODEX_DEFAULT_CWD,
    stdin: "pipe",
    stdout: "pipe",
    stderr: "pipe",
  });

  if (params.signal) {
    params.signal.addEventListener("abort", () => {
      process.kill();
    });
  }

  if (process.stdin) {
    process.stdin.write(params.prompt);
    process.stdin.end();
  }

  const timeout = setTimeout(() => {
    process.kill();
  }, env.CODEX_PROCESS_TIMEOUT * 1000);

  try {
    await Promise.all([
      forwardLines(process.stdout, "stdout", params.onEvent),
      forwardLines(process.stderr, "stderr", params.onEvent),
    ]);

    const code = await process.exited;
    await params.onEvent({ type: "process.exited", exit_code: code });
  } finally {
    clearTimeout(timeout);
  }
}
