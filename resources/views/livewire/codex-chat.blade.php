<div
    wire:ignore
    x-data="codexConsole({
        streamUrl: @js($streamUrl),
        csrfToken: @js(csrf_token()),
        defaultCwd: @js($defaultCwd),
        defaultModel: @js($defaultModel),
        defaultFullAuto: @js($defaultFullAuto),
    })"
    x-init="init()"
    class="codex-shell"
>
    <div class="codex-aurora"></div>

    <main class="mx-auto max-w-7xl p-4 md:p-8">
        <section class="codex-frame">
            <header class="codex-header">
                <div>
                    <p class="codex-kicker">Livewire + Laravel + Codex CLI</p>
                    <h1>Codex Stream Console</h1>
                </div>
                <div class="codex-status-group">
                    <span class="codex-pill" :class="isStreaming ? 'codex-pill-live' : 'codex-pill-idle'">
                        <span class="codex-dot"></span>
                        <span x-text="statusText"></span>
                    </span>
                    <span class="codex-pill codex-pill-mono">
                        Session:
                        <strong x-text="sessionId ? sessionId.slice(0, 8) : 'new'"></strong>
                    </span>
                </div>
            </header>

            <div class="grid gap-4 lg:grid-cols-[2fr_1fr]">
                <section class="codex-panel">
                    <div x-ref="transcript" class="codex-transcript">
                        <template x-for="message in messages" :key="message.id">
                            <article class="codex-msg" :class="`codex-msg-${message.role}`">
                                <h3 x-text="message.label"></h3>
                                <p x-text="message.text"></p>
                            </article>
                        </template>
                    </div>

                    <form class="codex-composer" @submit.prevent="sendPrompt()">
                        <label class="codex-label" for="prompt">Prompt</label>
                        <textarea
                            id="prompt"
                            x-model="prompt"
                            rows="4"
                            required
                            placeholder="Describe the change you want Codex to make..."
                        ></textarea>
                        <div class="codex-actions">
                            <button type="submit" class="codex-btn codex-btn-primary" :disabled="isStreaming || !prompt.trim()">
                                Send
                            </button>
                            <button type="button" class="codex-btn codex-btn-quiet" @click="stopStream()" :disabled="!isStreaming">
                                Stop
                            </button>
                            <button type="button" class="codex-btn codex-btn-quiet" @click="resetSession()" :disabled="isStreaming">
                                New Session
                            </button>
                        </div>
                    </form>
                </section>

                <aside class="codex-panel codex-sidebar">
                    <div>
                        <label class="codex-label" for="cwd">Working directory</label>
                        <input id="cwd" x-model="cwd" type="text" autocomplete="off">
                    </div>

                    <div>
                        <label class="codex-label" for="model">Model (optional)</label>
                        <input id="model" x-model="model" type="text" autocomplete="off" placeholder="o3">
                    </div>

                    <label class="codex-toggle">
                        <input type="checkbox" x-model="fullAuto">
                        <span>Enable <code>--full-auto</code></span>
                    </label>

                    <details class="codex-log">
                        <summary>CLI Logs (<span x-text="logs.length"></span>)</summary>
                        <div>
                            <template x-for="entry in logs" :key="entry.id">
                                <p>
                                    <span x-text="entry.stream"></span>
                                    <span x-text="entry.message"></span>
                                </p>
                            </template>
                        </div>
                    </details>
                </aside>
            </div>
        </section>
    </main>
</div>

@once
    <script>
        function codexConsole(config) {
            return {
                prompt: '',
                cwd: config.defaultCwd ?? '',
                model: config.defaultModel ?? '',
                fullAuto: Boolean(config.defaultFullAuto),
                sessionId: null,
                isStreaming: false,
                statusText: 'Idle',
                messages: [],
                logs: [],
                streamController: null,
                messageCounter: 0,
                assistantMessageId: null,

                init() {
                    this.addMessage('system', 'System', 'Ready. Send a prompt to start a Codex session.');
                },

                async sendPrompt() {
                    const bodyPrompt = this.prompt.trim();

                    if (!bodyPrompt || this.isStreaming) {
                        return;
                    }

                    this.addMessage('user', 'You', bodyPrompt);
                    this.prompt = '';
                    this.assistantMessageId = this.addMessage('assistant', 'Codex', '');
                    this.statusText = 'Streaming';
                    this.isStreaming = true;

                    try {
                        await this.consumeStream(bodyPrompt);
                    } catch (error) {
                        this.pruneEmptyAssistantMessage();

                        if (error?.name === 'AbortError') {
                            this.addMessage('system', 'System', 'Streaming stopped.');
                        } else {
                            this.addMessage('error', 'Error', error.message || 'Stream failed.');
                        }
                    } finally {
                        this.isStreaming = false;
                        this.streamController = null;
                        this.statusText = 'Idle';
                    }
                },

                stopStream() {
                    if (this.streamController) {
                        this.streamController.abort();
                    }
                },

                resetSession() {
                    this.sessionId = null;
                    this.logs = [];
                    this.messages = [];
                    this.assistantMessageId = null;
                    this.addMessage('system', 'System', 'Session reset. Next prompt starts a new Codex thread.');
                },

                async consumeStream(prompt) {
                    this.streamController = new AbortController();
                    const response = await fetch(config.streamUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'text/event-stream',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify({
                            prompt,
                            session_id: this.sessionId,
                            model: this.model || null,
                            full_auto: this.fullAuto,
                            cwd: this.cwd || null,
                        }),
                        signal: this.streamController.signal,
                    });

                    if (!response.ok || !response.body) {
                        throw new Error(await this.readErrorMessage(response));
                    }

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';

                    while (true) {
                        const { value, done } = await reader.read();

                        if (done) {
                            break;
                        }

                        buffer += decoder.decode(value, { stream: true });
                        ({ buffer } = this.parseSseBuffer(buffer));
                    }

                    ({ buffer } = this.parseSseBuffer(buffer + decoder.decode()));
                },

                parseSseBuffer(buffer) {
                    const normalized = buffer.replace(/\r/g, '');
                    let rest = normalized;
                    let boundary = rest.indexOf('\n\n');

                    while (boundary !== -1) {
                        const block = rest.slice(0, boundary).trim();
                        rest = rest.slice(boundary + 2);

                        if (block !== '') {
                            this.handleSseBlock(block);
                        }

                        boundary = rest.indexOf('\n\n');
                    }

                    return { buffer: rest };
                },

                handleSseBlock(block) {
                    let eventName = 'update';
                    const dataLines = [];

                    for (const line of block.split('\n')) {
                        if (line.startsWith('event:')) {
                            eventName = line.slice(6).trim();
                        } else if (line.startsWith('data:')) {
                            dataLines.push(line.slice(5).trimStart());
                        }
                    }

                    if (dataLines.length === 0) {
                        return;
                    }

                    const rawData = dataLines.join('\n');
                    let payload = rawData;

                    try {
                        payload = JSON.parse(rawData);
                    } catch (error) {
                        payload = { type: 'invalid.payload', raw: rawData };
                    }

                    if (eventName === 'meta') {
                        this.handleMetaEvent(payload);
                    }

                    if (eventName === 'codex') {
                        this.handleCodexEvent(payload);
                    }

                    if (eventName === 'done') {
                        this.handleDoneEvent(payload);
                    }
                },

                handleMetaEvent(payload) {
                    if (!payload) {
                        return;
                    }

                    if (payload.session_id) {
                        this.sessionId = payload.session_id;
                    }
                },

                handleDoneEvent(payload) {
                    if (!payload) {
                        return;
                    }
                },

                handleCodexEvent(payload) {
                    if (!payload || !payload.type) {
                        return;
                    }

                    if (payload.type === 'thread.started' && payload.thread_id) {
                        this.sessionId = payload.thread_id;
                    }

                    if (payload.type === 'item.completed' && payload.item?.type === 'agent_message') {
                        this.setAssistantText(payload.item.text ?? '');
                    }

                    if (payload.type === 'error') {
                        this.addMessage('error', 'Codex', payload.message ?? 'Unknown stream error.');
                    }

                    if (payload.type === 'turn.failed') {
                        const message = payload.error?.message ?? 'Turn failed.';
                        this.addMessage('error', 'Codex', message);
                    }

                    if (payload.type === 'cli.raw') {
                        this.logs.push({
                            id: this.nextId(),
                            stream: payload.stream ?? 'cli',
                            message: payload.message ?? '',
                        });
                    }

                    if (payload.type === 'process.exited' && payload.exit_code !== 0) {
                        this.pruneEmptyAssistantMessage();
                        this.addMessage('error', 'System', `Codex process exited with code ${payload.exit_code}.`);
                    }
                },

                async readErrorMessage(response) {
                    const contentType = response.headers.get('content-type') ?? '';

                    if (contentType.includes('application/json')) {
                        const json = await response.json().catch(() => null);

                        if (json?.errors && typeof json.errors === 'object') {
                            const validationError = Object.values(json.errors)
                                .flat()
                                .find((message) => typeof message === 'string');

                            if (validationError) {
                                return validationError;
                            }
                        }

                        if (typeof json?.message === 'string' && json.message.trim() !== '') {
                            return json.message;
                        }
                    }

                    const message = await response.text().catch(() => '');

                    return message || `Request failed (${response.status}).`;
                },

                setAssistantText(text) {
                    const target = this.messages.find((message) => message.id === this.assistantMessageId);

                    if (target) {
                        target.text = text;
                        this.scrollToBottom();
                    }
                },

                addMessage(role, label, text) {
                    const id = this.nextId();
                    this.messages.push({ id, role, label, text });
                    this.scrollToBottom();
                    return id;
                },

                pruneEmptyAssistantMessage() {
                    const index = this.messages.findIndex((message) => message.id === this.assistantMessageId);

                    if (index === -1) {
                        return;
                    }

                    const assistantMessage = this.messages[index];

                    if (!assistantMessage.text || assistantMessage.text.trim() === '') {
                        this.messages.splice(index, 1);
                        this.assistantMessageId = null;
                    }
                },

                nextId() {
                    this.messageCounter += 1;
                    return this.messageCounter;
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const transcript = this.$refs.transcript;
                        transcript.scrollTop = transcript.scrollHeight;
                    });
                },
            };
        }
    </script>
@endonce
