import { useEffect, useMemo, useRef, useState } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import type { CodexEvent, DirectoryListing, RuntimeConfig, StreamPayload } from "@/types";

type Message = {
  id: number;
  role: "system" | "user" | "assistant" | "error";
  label: string;
  text: string;
};

const initialMessage: Message = {
  id: 1,
  role: "system",
  label: "System",
  text: "Ready. Configure runtime options and send a prompt.",
};

function toLabel(path: string, workspace: string) {
  if (path === workspace) {
    return "Workspace root";
  }

  if (!path.startsWith(workspace)) {
    return path;
  }

  const relative = path.slice(workspace.length).replace(/^[/\\]+/, "");
  return relative || "Workspace root";
}

async function getJson<T>(url: string): Promise<T> {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Request failed: ${response.status}`);
  }

  return (await response.json()) as T;
}

export default function HomeRoute() {
  const [config, setConfig] = useState<RuntimeConfig | null>(null);
  const [listing, setListing] = useState<DirectoryListing | null>(null);
  const [cwdListing, setCwdListing] = useState<DirectoryListing | null>(null);
  const [messages, setMessages] = useState<Message[]>([initialMessage]);
  const messageIdRef = useRef(2);
  const [prompt, setPrompt] = useState("");
  const [sessionId, setSessionId] = useState<string | null>(null);
  const [assistantStream, setAssistantStream] = useState("");
  const [logs, setLogs] = useState<string[]>([]);
  const [streaming, setStreaming] = useState(false);

  const [workspaceRoot, setWorkspaceRoot] = useState("");
  const [cwd, setCwd] = useState("");
  const [model, setModel] = useState("");
  const [reasoningEffort, setReasoningEffort] = useState("");
  const [fullAuto, setFullAuto] = useState(false);
  const [sandboxMode, setSandboxMode] = useState("");
  const [approvalPolicy, setApprovalPolicy] = useState("");
  const [webSearch, setWebSearch] = useState(false);
  const [additionalDirsText, setAdditionalDirsText] = useState("");

  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    const load = async () => {
      const runtime = await getJson<RuntimeConfig>("/api/codex/config");
      setConfig(runtime);
      setWorkspaceRoot(runtime.defaults.workspaceRoot);
      setCwd(runtime.defaults.cwd);
      setModel(runtime.defaults.model);
      setReasoningEffort(runtime.defaults.reasoningEffort);
      setFullAuto(runtime.defaults.fullAuto);
      setSandboxMode(runtime.defaults.sandboxMode);
      setApprovalPolicy(runtime.defaults.approvalPolicy);
      setWebSearch(runtime.defaults.webSearch);

      const workspace = await getJson<DirectoryListing>("/api/codex/directories");
      setListing(workspace);

      const cwdData = await getJson<DirectoryListing>(
        `/api/codex/directories?path=${encodeURIComponent(runtime.defaults.cwd)}`,
      );
      setCwdListing(cwdData);
    };

    void load();
  }, []);

  const workspaceOptions = useMemo(() => {
    if (!listing) {
      return [] as string[];
    }

    const unique = new Set<string>([
      listing.current,
      ...(listing.parent ? [listing.parent] : []),
      ...listing.children,
      ...(listing.home ? [listing.home] : []),
      listing.system_root,
    ]);

    return [...unique];
  }, [listing]);

  const cwdOptions = useMemo(() => {
    if (!cwdListing) {
      return [cwd].filter(Boolean);
    }

    const within = cwdListing.children.filter((child) => child.startsWith(workspaceRoot));
    return [...new Set([cwdListing.current, ...within])];
  }, [cwd, cwdListing, workspaceRoot]);

  const addMessage = (role: Message["role"], label: string, text: string) => {
    const id = messageIdRef.current;
    messageIdRef.current += 1;
    setMessages((current) => [...current, { id, role, label, text }]);
  };

  const refreshWorkspace = async (path: string) => {
    const data = await getJson<DirectoryListing>(
      `/api/codex/directories?path=${encodeURIComponent(path)}`,
    );
    setListing(data);
    setWorkspaceRoot(data.current);
    setCwd(data.current);
    setCwdListing(data);
  };

  const refreshCwd = async (path: string) => {
    const data = await getJson<DirectoryListing>(
      `/api/codex/directories?path=${encodeURIComponent(path)}`,
    );
    if (!data.current.startsWith(workspaceRoot) && data.current !== workspaceRoot) {
      setCwd(workspaceRoot);
      return;
    }

    setCwdListing(data);
    setCwd(data.current);
  };

  const parseAdditionalDirs = () => {
    return [
      ...new Set(
        additionalDirsText
          .split(/\r\n|\r|\n/)
          .map((line) => line.trim())
          .filter(Boolean),
      ),
    ];
  };

  const handleCodexEvent = (event: CodexEvent, assistantText: { current: string }) => {
    const type = event.type;

    if (type === "thread.started" && typeof event.thread_id === "string") {
      setSessionId(event.thread_id);
      return;
    }

    if (type === "item.completed" && typeof event.item === "object" && event.item) {
      const item = event.item as Record<string, unknown>;
      if (item.type === "agent_message") {
        const text = typeof item.text === "string" ? item.text : "";
        assistantText.current = text;
        setAssistantStream(text);
      }
      return;
    }

    if (type === "cli.raw") {
      const stream = typeof event.stream === "string" ? event.stream : "cli";
      const message = typeof event.message === "string" ? event.message : "";
      setLogs((current) => [...current, `[${stream}] ${message}`]);
      return;
    }

    if (type === "error") {
      const message = typeof event.message === "string" ? event.message : "Unknown stream error.";
      addMessage("error", "Codex", message);
      return;
    }

    if (type === "turn.failed" && typeof event.error === "object" && event.error) {
      const payload = event.error as Record<string, unknown>;
      const message = typeof payload.message === "string" ? payload.message : "Turn failed.";
      addMessage("error", "Codex", message);
      return;
    }

    if (type === "process.exited" && typeof event.exit_code === "number" && event.exit_code !== 0) {
      addMessage("error", "System", `Codex process exited with code ${event.exit_code}.`);
    }
  };

  const handleSubmit = async () => {
    if (streaming) {
      return;
    }

    const trimmed = prompt.trim();
    if (!trimmed) {
      return;
    }

    const payload: StreamPayload = {
      prompt: trimmed,
      session_id: sessionId,
      workspace_root: workspaceRoot,
      model,
      reasoning_effort: reasoningEffort,
      full_auto: fullAuto,
      cwd,
      sandbox_mode: sandboxMode,
      approval_policy: approvalPolicy,
      web_search: webSearch,
      add_dirs: parseAdditionalDirs(),
    };

    addMessage("user", "You", trimmed);
    setPrompt("");
    setAssistantStream("");
    setLogs([]);
    setStreaming(true);

    const assistantText = { current: "" };

    try {
      const controller = new AbortController();
      abortRef.current = controller;

      const response = await fetch("/api/codex/stream", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
        signal: controller.signal,
      });

      if (!response.ok || !response.body) {
        throw new Error(`Stream failed (${response.status})`);
      }

      const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();

      let buffer = "";

      while (true) {
        const { done, value } = await reader.read();
        if (done) {
          break;
        }

        buffer += value;

        while (true) {
          const split = buffer.indexOf("\n\n");
          if (split === -1) {
            break;
          }

          const frame = buffer.slice(0, split);
          buffer = buffer.slice(split + 2);

          const eventLine = frame.split("\n").find((line) => line.startsWith("event:"));
          const dataLine = frame.split("\n").find((line) => line.startsWith("data:"));

          if (!eventLine || !dataLine) {
            continue;
          }

          const eventName = eventLine.replace(/^event:\s*/, "");
          if (eventName === "done") {
            continue;
          }

          const rawData = dataLine.replace(/^data:\s*/, "");
          const parsed = JSON.parse(rawData) as CodexEvent;
          handleCodexEvent(parsed, assistantText);
        }
      }

      if (assistantText.current.trim()) {
        addMessage("assistant", "Codex", assistantText.current);
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : "Unexpected stream failure.";
      if (message !== "The operation was aborted.") {
        addMessage("error", "System", message);
      } else {
        addMessage("system", "System", "Streaming stopped.");
      }
    } finally {
      setStreaming(false);
      abortRef.current = null;
      setAssistantStream("");
    }
  };

  const sessionPreview = sessionId ? sessionId.slice(0, 8) : "new";

  if (!config || !listing) {
    return <main className="mx-auto p-6 text-sm text-zinc-400">Loading console...</main>;
  }

  return (
    <main className="mx-auto w-full max-w-7xl p-4 md:p-8">
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="font-mono text-xs uppercase tracking-[0.18em] text-cyan-300/80">
            Bun + React Router + Codex CLI
          </p>
          <h1 className="text-2xl font-semibold md:text-3xl">Codex Stream Console</h1>
        </div>
        <div className="flex items-center gap-2">
          <Badge className={streaming ? "border-cyan-300/50 text-cyan-200" : ""}>
            {streaming ? "Streaming" : "Idle"}
          </Badge>
          <Badge>Session: {sessionPreview}</Badge>
        </div>
      </div>

      <section className="grid gap-4 lg:grid-cols-[2fr_1fr]">
        <Card className="space-y-4">
          <div className="max-h-[55vh] space-y-3 overflow-y-auto rounded-lg border border-zinc-800 bg-zinc-950/60 p-3">
            {messages.map((message) => (
              <article
                className={`rounded-md border p-3 text-sm ${
                  message.role === "user"
                    ? "border-cyan-900/80 bg-cyan-950/20"
                    : message.role === "assistant"
                      ? "border-emerald-900/80 bg-emerald-950/20"
                      : message.role === "error"
                        ? "border-rose-900/80 bg-rose-950/20"
                        : "border-zinc-800 bg-zinc-900/70"
                }`}
                key={message.id}
              >
                <h3 className="mb-1 font-mono text-xs uppercase tracking-[0.12em] text-zinc-400">
                  {message.label}
                </h3>
                <p className="whitespace-pre-wrap">{message.text}</p>
              </article>
            ))}

            {streaming && (
              <article className="rounded-md border border-emerald-900/60 bg-emerald-950/15 p-3 text-sm">
                <h3 className="mb-1 font-mono text-xs uppercase tracking-[0.12em] text-zinc-400">
                  Codex
                </h3>
                <p className="whitespace-pre-wrap">{assistantStream}</p>
              </article>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="prompt">Prompt</Label>
            <Textarea
              id="prompt"
              onChange={(event) => setPrompt(event.target.value)}
              placeholder="Describe the change you want Codex to make..."
              rows={4}
              value={prompt}
            />
          </div>

          <div className="flex flex-wrap gap-2">
            <Button disabled={streaming} onClick={() => void handleSubmit()} type="button">
              Send
            </Button>
            <Button
              disabled={!streaming}
              onClick={() => {
                abortRef.current?.abort();
              }}
              type="button"
              variant="ghost"
            >
              Stop
            </Button>
            <Button
              disabled={streaming}
              onClick={() => {
                setSessionId(null);
                setMessages([initialMessage]);
                messageIdRef.current = 2;
                setLogs([]);
              }}
              type="button"
              variant="outline"
            >
              New Session
            </Button>
          </div>
        </Card>

        <div className="space-y-4">
          <Card className="space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="font-mono text-sm uppercase tracking-[0.12em] text-zinc-300">
                Workspace
              </h2>
              <Button
                onClick={() => void refreshWorkspace(config.defaults.workspaceRoot)}
                size="sm"
                type="button"
                variant="outline"
              >
                Root
              </Button>
            </div>

            <Label>Workspace location</Label>
            <Select
              onChange={(value) => {
                if (value === "__up_workspace__") {
                  if (listing.parent) {
                    void refreshWorkspace(listing.parent);
                  }
                  return;
                }

                void refreshWorkspace(value);
              }}
              options={[
                {
                  value: "__up_workspace__",
                  label: listing.parent
                    ? `Up one level (${listing.parent})`
                    : "Up one level (at top)",
                },
                ...workspaceOptions.map((path) => ({ value: path, label: path })),
              ]}
              value={workspaceRoot}
            />

            <Label>Working directory</Label>
            <Select
              onChange={(value) => {
                if (value === "__up__") {
                  if (cwdListing?.parent?.startsWith(workspaceRoot)) {
                    void refreshCwd(cwdListing.parent);
                  }
                  return;
                }

                void refreshCwd(value);
              }}
              options={[
                {
                  value: "__up__",
                  label: cwdListing?.parent?.startsWith(workspaceRoot)
                    ? `Up one level (${toLabel(cwdListing.parent, workspaceRoot)})`
                    : "Up one level (at workspace root)",
                },
                ...cwdOptions.map((path) => ({ value: path, label: toLabel(path, workspaceRoot) })),
              ]}
              value={cwd}
            />

            <Label htmlFor="add_dirs">Additional writable directories</Label>
            <Textarea
              id="add_dirs"
              onChange={(event) => setAdditionalDirsText(event.target.value)}
              placeholder="One path per line"
              rows={3}
              value={additionalDirsText}
            />
          </Card>

          <Card className="space-y-3">
            <h2 className="font-mono text-sm uppercase tracking-[0.12em] text-zinc-300">Model</h2>

            <Label>Model</Label>
            <Select
              onChange={setModel}
              options={config.options.models.map((option) => ({
                value: option.value,
                label: option.value,
              }))}
              value={model}
            />

            <Label>Reasoning effort</Label>
            <Select
              onChange={setReasoningEffort}
              options={config.options.reasoningEfforts.map((option) => ({
                value: option.value,
                label: option.value,
              }))}
              value={reasoningEffort}
            />
          </Card>

          <Card className="space-y-3">
            <h2 className="font-mono text-sm uppercase tracking-[0.12em] text-zinc-300">
              Execution
            </h2>
            <Switch checked={fullAuto} label="Enable --full-auto" onCheckedChange={setFullAuto} />
            <Switch
              checked={webSearch}
              label="Enable live web search (--search)"
              onCheckedChange={setWebSearch}
            />

            <Label>Sandbox</Label>
            <Select
              disabled={fullAuto}
              onChange={setSandboxMode}
              options={config.options.sandboxModes.map((option) => ({
                value: option.value,
                label: option.value,
              }))}
              value={sandboxMode}
            />

            <Label>Approval policy</Label>
            <Select
              disabled={fullAuto}
              onChange={setApprovalPolicy}
              options={config.options.approvalPolicies.map((option) => ({
                value: option.value,
                label: option.value,
              }))}
              value={approvalPolicy}
            />
          </Card>

          <Card>
            <details>
              <summary className="cursor-pointer font-mono text-xs uppercase tracking-[0.14em] text-zinc-300">
                CLI logs ({logs.length})
              </summary>
              <pre className="mt-3 max-h-48 overflow-y-auto rounded-md border border-zinc-800 bg-zinc-950 p-3 font-mono text-xs text-zinc-300">
                {logs.join("\n")}
              </pre>
            </details>
          </Card>
        </div>
      </section>
    </main>
  );
}
