<div
    wire:ignore
    x-data="codexConsole({
        streamUrl: @js($streamUrl),
        directoriesUrl: @js($directoriesUrl),
        csrfToken: @js(csrf_token()),
        defaultWorkspaceRoot: @js($defaultWorkspaceRoot),
        defaultCwd: @js($defaultCwd),
        defaultModel: @js($defaultModel),
        defaultReasoningEffort: @js($defaultReasoningEffort),
        defaultFullAuto: @js($defaultFullAuto),
        defaultSandboxMode: @js($defaultSandboxMode),
        defaultApprovalPolicy: @js($defaultApprovalPolicy),
        defaultWebSearch: @js($defaultWebSearch),
        modelOptions: @js($models),
        reasoningOptions: @js($reasoningEfforts),
        sandboxOptions: @js($sandboxModes),
        approvalOptions: @js($approvalPolicies),
    })"
    x-init="init()"
    class="codex-shell"
>
    <div class="codex-aurora"></div>

    <main class="codex-main mx-auto max-w-7xl">
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

            <div class="codex-layout grid gap-4 lg:grid-cols-[2fr_1fr]">
                <section class="codex-panel codex-chat-panel">
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
                    <section class="codex-card">
                        <div class="codex-card-head">
                            <h2>Workspace</h2>
                            <button type="button" class="codex-btn codex-btn-chip" @click="resetWorkspaceRoot()" :disabled="isStreaming || isDirectoryLoading">
                                Root
                            </button>
                        </div>

                        <label class="codex-label" for="workspaceRoot">Workspace location</label>
                        <select id="workspaceRoot" :value="workspaceRoot" @change="selectWorkspaceRoot($event.target.value)" :disabled="isDirectoryLoading">
                            <option value="__up_workspace__" :disabled="!canGoUpWorkspace()" x-text="workspaceUpLabel()"></option>
                            <template x-for="path in workspaceOptions" :key="`workspace-${path}`">
                                <option :value="path" x-text="workspaceOptionLabel(path)"></option>
                            </template>
                        </select>
                        <p class="codex-hint" x-text="workspaceRoot"></p>

                        <label class="codex-label" for="cwd">Working directory</label>
                        <select id="cwd" :value="cwd" @change="selectWorkingDirectory($event.target.value)" :disabled="isDirectoryLoading">
                            <option value="__up__" :disabled="!canGoUpDirectory()" x-text="upDirectoryLabel()"></option>
                            <template x-for="path in cwdOptions()" :key="`cwd-${path}`">
                                <option :value="path" x-text="pathOptionLabel(path)"></option>
                            </template>
                        </select>
                        <p class="codex-hint" x-text="cwd"></p>

                        <label class="codex-label" for="addDirs">Additional writable directories</label>
                        <textarea
                            id="addDirs"
                            x-model="additionalDirsText"
                            rows="3"
                            placeholder="One path per line"
                        ></textarea>
                        <p class="codex-hint">Maps to <code>--add-dir</code>. Paths must be inside your workspace root.</p>
                    </section>

                    <section class="codex-card">
                        <h2>Model</h2>

                        <label class="codex-label" for="model">Model</label>
                        <select id="model" x-model="model">
                            <template x-for="option in modelOptions" :key="option.value">
                                <option :value="option.value" x-text="option.value"></option>
                            </template>
                        </select>
                        <p class="codex-hint" x-text="selectedDescription(modelOptions, model)"></p>

                        <label class="codex-label" for="reasoningEffort">Reasoning effort</label>
                        <select id="reasoningEffort" x-model="reasoningEffort">
                            <template x-for="option in reasoningOptions" :key="option.value">
                                <option :value="option.value" x-text="option.value"></option>
                            </template>
                        </select>
                        <p class="codex-hint" x-text="selectedDescription(reasoningOptions, reasoningEffort)"></p>
                    </section>

                    <section class="codex-card">
                        <h2>Execution</h2>

                        <label class="codex-toggle">
                            <input type="checkbox" x-model="fullAuto">
                            <span>Enable <code>--full-auto</code></span>
                        </label>

                        <label class="codex-toggle">
                            <input type="checkbox" x-model="webSearch">
                            <span>Enable live web search (<code>--search</code>)</span>
                        </label>

                        <label class="codex-label" for="sandboxMode">Sandbox</label>
                        <select id="sandboxMode" x-model="sandboxMode" :disabled="fullAuto">
                            <template x-for="option in sandboxOptions" :key="option.value">
                                <option :value="option.value" x-text="option.value"></option>
                            </template>
                        </select>
                        <p class="codex-hint" x-text="selectedDescription(sandboxOptions, sandboxMode)"></p>

                        <label class="codex-label" for="approvalPolicy">Approval policy</label>
                        <select id="approvalPolicy" x-model="approvalPolicy" :disabled="fullAuto">
                            <template x-for="option in approvalOptions" :key="option.value">
                                <option :value="option.value" x-text="option.value"></option>
                            </template>
                        </select>
                        <p class="codex-hint" x-text="selectedDescription(approvalOptions, approvalPolicy)"></p>
                    </section>

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
                workspaceRoot: config.defaultWorkspaceRoot ?? '',
                defaultWorkspaceRoot: config.defaultWorkspaceRoot ?? '',
                workspaceOptions: [],
                workspaceParent: null,
                systemRoot: '/',
                homeDirectory: null,
                cwdSuggestions: [],
                cwdParent: null,
                isDirectoryLoading: false,
                modelOptions: config.modelOptions ?? [],
                reasoningOptions: config.reasoningOptions ?? [],
                sandboxOptions: config.sandboxOptions ?? [],
                approvalOptions: config.approvalOptions ?? [],
                cwd: config.defaultCwd ?? '',
                model: config.defaultModel ?? '',
                reasoningEffort: config.defaultReasoningEffort ?? 'medium',
                fullAuto: Boolean(config.defaultFullAuto),
                sandboxMode: config.defaultSandboxMode ?? 'workspace-write',
                approvalPolicy: config.defaultApprovalPolicy ?? 'on-request',
                webSearch: Boolean(config.defaultWebSearch),
                additionalDirsText: '',
                sessionId: null,
                isStreaming: false,
                statusText: 'Idle',
                messages: [],
                logs: [],
                streamController: null,
                messageCounter: 0,
                assistantMessageId: null,
                hasInitialized: false,

                async init() {
                    if (this.hasInitialized) {
                        return;
                    }

                    this.hasInitialized = true;
                    await this.refreshWorkspaceBrowser(this.workspaceRoot, {
                        setAsCurrent: true,
                        resetCwd: true,
                        initialCwd: config.defaultCwd ?? null,
                    });
                    this.addMessage('system', 'System', 'Ready. Use the right-side controls to configure Codex, then send a prompt.');
                },

                selectedDescription(options, value) {
                    const target = options.find((option) => option.value === value);

                    if (!target) {
                        return '';
                    }

                    return target.description ?? '';
                },

                parseAdditionalDirectories() {
                    const lines = this.additionalDirsText
                        .split('\n')
                        .map((line) => line.trim())
                        .filter((line) => line !== '' && line !== this.cwd);

                    return [...new Set(lines)];
                },

                async fetchDirectoryListing(path) {
                    const url = new URL(config.directoriesUrl, window.location.origin);
                    const targetPath = String(path ?? '').trim();

                    if (targetPath !== '') {
                        url.searchParams.set('path', targetPath);
                    }

                    const response = await fetch(url.toString(), {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error(await this.readErrorMessage(response));
                    }

                    const payload = await response.json().catch(() => null);

                    if (!payload?.current || !Array.isArray(payload?.children)) {
                        throw new Error('Failed to read directories.');
                    }

                    return payload;
                },

                uniquePaths(paths) {
                    const unique = [];
                    const seen = new Set();

                    for (const path of paths) {
                        if (typeof path !== 'string' || path.trim() === '') {
                            continue;
                        }

                        if (!seen.has(path)) {
                            unique.push(path);
                            seen.add(path);
                        }
                    }

                    return unique;
                },

                async refreshWorkspaceBrowser(path, { setAsCurrent = true, resetCwd = true, initialCwd = null } = {}) {
                    this.isDirectoryLoading = true;

                    try {
                        const listing = await this.fetchDirectoryListing(path);

                        this.systemRoot = listing.system_root ?? this.systemRoot;
                        this.homeDirectory = listing.home ?? this.homeDirectory;
                        this.workspaceParent = listing.parent ?? null;
                        this.workspaceOptions = this.uniquePaths([
                            listing.current,
                            listing.parent,
                            ...listing.children,
                            listing.home,
                            listing.system_root,
                        ]);

                        if (setAsCurrent) {
                            this.workspaceRoot = listing.current;
                        }

                        const targetCwd = resetCwd
                            ? (initialCwd && this.pathIsWithinWorkspace(initialCwd) ? initialCwd : this.workspaceRoot)
                            : (this.cwd || this.workspaceRoot);

                        await this.refreshCwdSuggestions(targetCwd);
                    } catch (error) {
                        this.addMessage('error', 'System', error?.message || 'Unable to load directories.');
                    } finally {
                        this.isDirectoryLoading = false;
                    }
                },

                workspaceOptionLabel(path) {
                    if (path === this.workspaceRoot) {
                        return `Current workspace (${path})`;
                    }

                    if (this.homeDirectory && path === this.homeDirectory) {
                        return `Home (${path})`;
                    }

                    if (path === this.systemRoot) {
                        return `System root (${path})`;
                    }

                    return path;
                },

                workspaceUpLabel() {
                    if (!this.workspaceParent) {
                        return 'Up one level (at top)';
                    }

                    return `Up one level (${this.workspaceParent})`;
                },

                canGoUpWorkspace() {
                    return this.workspaceParent !== null;
                },

                async selectWorkspaceRoot(value) {
                    if (value === '__up_workspace__') {
                        await this.goUpWorkspace();
                        return;
                    }

                    await this.refreshWorkspaceBrowser(value, {
                        setAsCurrent: true,
                        resetCwd: true,
                    });
                },

                async goUpWorkspace() {
                    if (!this.workspaceParent) {
                        return;
                    }

                    await this.refreshWorkspaceBrowser(this.workspaceParent, {
                        setAsCurrent: true,
                        resetCwd: true,
                    });
                },

                async resetWorkspaceRoot() {
                    await this.refreshWorkspaceBrowser(this.defaultWorkspaceRoot, {
                        setAsCurrent: true,
                        resetCwd: true,
                    });
                },

                async refreshCwdSuggestions(path) {
                    const listing = await this.fetchDirectoryListing(path || this.workspaceRoot);

                    if (!this.pathIsWithinWorkspace(listing.current)) {
                        this.cwd = this.workspaceRoot;
                        this.cwdParent = null;
                        this.cwdSuggestions = [this.workspaceRoot];

                        return;
                    }

                    this.cwd = listing.current;
                    this.cwdParent = listing.parent && this.pathIsWithinWorkspace(listing.parent)
                        ? listing.parent
                        : null;
                    this.cwdSuggestions = this.uniquePaths([
                        listing.current,
                        ...listing.children.filter((child) => this.pathIsWithinWorkspace(child)),
                    ]);
                },

                cwdOptions() {
                    const options = [...this.cwdSuggestions];

                    if (this.cwd && !options.includes(this.cwd)) {
                        options.unshift(this.cwd);
                    }

                    return [...new Set(options)];
                },

                pathOptionLabel(path) {
                    const normalizedRoot = this.normalizePath(this.workspaceRoot);
                    const normalizedPath = this.normalizePath(path);

                    if (normalizedPath === normalizedRoot) {
                        return 'Workspace root';
                    }

                    if (!normalizedPath.startsWith(normalizedRoot)) {
                        return path;
                    }

                    const suffix = normalizedPath.slice(normalizedRoot.length).replace(/^[/\\\\]/, '');

                    return suffix === '' ? 'Workspace root' : suffix;
                },

                async selectWorkingDirectory(value) {
                    if (value === '__up__') {
                        await this.goUpDirectory();
                        return;
                    }

                    await this.refreshCwdSuggestions(value);
                },

                upDirectoryLabel() {
                    const parent = this.cwdParent;

                    if (!parent) {
                        return 'Up one level (at workspace root)';
                    }

                    return `Up one level (${this.pathOptionLabel(parent)})`;
                },

                canGoUpDirectory() {
                    return this.cwdParent !== null;
                },

                async goUpDirectory() {
                    const parent = this.cwdParent;

                    if (parent) {
                        await this.refreshCwdSuggestions(parent);
                    }
                },

                pathIsWithinWorkspace(path) {
                    const normalizedPath = this.normalizePath(path);
                    const normalizedRoot = this.normalizePath(this.workspaceRoot);

                    if (!normalizedPath || !normalizedRoot) {
                        return false;
                    }

                    if (normalizedPath === normalizedRoot) {
                        return true;
                    }

                    if (normalizedRoot === '/' || /^[a-zA-Z]:[\\\\/]$/.test(normalizedRoot)) {
                        return normalizedPath.startsWith(normalizedRoot);
                    }

                    const separator = normalizedRoot.includes('\\') ? '\\' : '/';

                    return normalizedPath.startsWith(`${normalizedRoot}${separator}`);
                },

                normalizePath(path) {
                    const value = String(path ?? '');

                    if (value === '/' || /^[a-zA-Z]:[\\\\/]$/.test(value)) {
                        return value;
                    }

                    return value.replace(/[\\\\/]+$/, '');
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
                            workspace_root: this.workspaceRoot || null,
                            model: this.model || null,
                            reasoning_effort: this.reasoningEffort || null,
                            full_auto: this.fullAuto,
                            cwd: this.cwd || null,
                            sandbox_mode: this.sandboxMode || null,
                            approval_policy: this.approvalPolicy || null,
                            web_search: this.webSearch,
                            add_dirs: this.parseAdditionalDirectories(),
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
