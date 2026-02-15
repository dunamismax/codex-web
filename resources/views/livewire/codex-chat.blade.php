<div class="codex-shell">
    <div class="codex-aurora"></div>

    <main class="codex-main mx-auto max-w-7xl">
        <section class="codex-frame">
            <header class="codex-header">
                <div>
                    <p class="codex-kicker">Livewire + Laravel + Codex CLI</p>
                    <h1>Codex Stream Console</h1>
                </div>
                <div class="codex-status-group">
                    <span
                        class="codex-pill codex-pill-idle"
                        wire:loading.class="codex-pill-live"
                        wire:loading.class.remove="codex-pill-idle"
                        wire:target="sendPrompt"
                    >
                        <span class="codex-dot"></span>
                        <span wire:loading.remove wire:target="sendPrompt">Idle</span>
                        <span wire:loading wire:target="sendPrompt">Streaming</span>
                    </span>
                    <span class="codex-pill codex-pill-mono">
                        Session:
                        <strong wire:stream="session-preview">{{ $this->sessionPreviewText() }}</strong>
                    </span>
                </div>
            </header>

            <div class="codex-layout grid gap-4 lg:grid-cols-[2fr_1fr]">
                <section class="codex-panel codex-chat-panel">
                    <div class="codex-transcript" id="codex-transcript">
                        @foreach ($messages as $message)
                            <article class="codex-msg codex-msg-{{ $message['role'] }}" wire:key="message-{{ $message['id'] }}">
                                <h3>{{ $message['label'] }}</h3>
                                <p>{{ $message['text'] }}</p>
                            </article>
                        @endforeach

                        <article
                            class="codex-msg codex-msg-assistant hidden"
                            wire:loading.class.remove="hidden"
                            wire:target="sendPrompt"
                        >
                            <h3>Codex</h3>
                            <p wire:stream="assistant-output"></p>
                        </article>
                    </div>

                    <form class="codex-composer" wire:submit="sendPrompt">
                        <label class="codex-label" for="prompt">Prompt</label>
                        <textarea
                            id="prompt"
                            wire:model="prompt"
                            rows="4"
                            required
                            placeholder="Describe the change you want Codex to make..."
                        ></textarea>
                        @error('prompt')
                            <p class="codex-hint">{{ $message }}</p>
                        @enderror

                        <div class="codex-actions">
                            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="sendPrompt,resetSession">
                                Send
                            </flux:button>
                            <flux:button type="button" variant="ghost" wire:click.async="stopStream" wire:loading.attr="disabled" wire:target="stopStream">
                                Stop
                            </flux:button>
                            <flux:button type="button" variant="ghost" wire:click="resetSession" wire:loading.attr="disabled" wire:target="sendPrompt,resetSession">
                                New Session
                            </flux:button>
                        </div>
                    </form>
                </section>

                <aside class="codex-panel codex-sidebar">
                    <section class="codex-card">
                        <div class="codex-card-head">
                            <h2>Workspace</h2>
                            <flux:button type="button" size="sm" variant="filled" wire:click="resetWorkspaceRoot" wire:loading.attr="disabled" wire:target="sendPrompt,resetWorkspaceRoot,goUpWorkspace,updatedWorkspaceRootSelection">
                                Root
                            </flux:button>
                        </div>

                        <label class="codex-label" for="workspaceRoot">Workspace location</label>
                        <select id="workspaceRoot" wire:model.live="workspaceRootSelection" wire:loading.attr="disabled" wire:target="sendPrompt,resetWorkspaceRoot,goUpWorkspace,updatedWorkspaceRootSelection">
                            <option value="__up_workspace__" @disabled(! $this->canGoUpWorkspace())>{{ $this->workspaceUpLabel() }}</option>
                            @foreach ($workspaceOptions as $path)
                                <option value="{{ $path }}">{{ $this->workspaceOptionLabel($path) }}</option>
                            @endforeach
                        </select>
                        <p class="codex-hint">{{ $workspaceRoot }}</p>

                        <label class="codex-label" for="cwd">Working directory</label>
                        <select id="cwd" wire:model.live="cwdSelection" wire:loading.attr="disabled" wire:target="sendPrompt,updatedCwdSelection,goUpDirectory,updatedWorkspaceRootSelection,resetWorkspaceRoot,goUpWorkspace">
                            <option value="__up__" @disabled(! $this->canGoUpDirectory())>{{ $this->upDirectoryLabel() }}</option>
                            @foreach ($this->cwdOptions() as $path)
                                <option value="{{ $path }}">{{ $this->pathOptionLabel($path) }}</option>
                            @endforeach
                        </select>
                        <p class="codex-hint">{{ $cwd }}</p>

                        <label class="codex-label" for="addDirs">Additional writable directories</label>
                        <textarea
                            id="addDirs"
                            wire:model="additionalDirsText"
                            rows="3"
                            placeholder="One path per line"
                        ></textarea>
                        <p class="codex-hint">Maps to <code>--add-dir</code>. Paths must be inside your workspace root.</p>
                    </section>

                    <section class="codex-card">
                        <h2>Model</h2>

                        <label class="codex-label" for="model">Model</label>
                        <select id="model" wire:model="model">
                            @foreach ($models as $option)
                                <option value="{{ $option['value'] }}">{{ $option['value'] }}</option>
                            @endforeach
                        </select>
                        <p class="codex-hint">{{ $this->selectedDescription($models, $model) }}</p>

                        <label class="codex-label" for="reasoningEffort">Reasoning effort</label>
                        <select id="reasoningEffort" wire:model="reasoningEffort">
                            @foreach ($reasoningEfforts as $option)
                                <option value="{{ $option['value'] }}">{{ $option['value'] }}</option>
                            @endforeach
                        </select>
                        <p class="codex-hint">{{ $this->selectedDescription($reasoningEfforts, $reasoningEffort) }}</p>
                    </section>

                    <section class="codex-card">
                        <h2>Execution</h2>

                        <label class="codex-toggle">
                            <input type="checkbox" wire:model="fullAuto">
                            <span>Enable <code>--full-auto</code></span>
                        </label>

                        <label class="codex-toggle">
                            <input type="checkbox" wire:model="webSearch">
                            <span>Enable live web search (<code>--search</code>)</span>
                        </label>

                        <label class="codex-label" for="sandboxMode">Sandbox</label>
                        <select id="sandboxMode" wire:model="sandboxMode" @disabled($fullAuto)>
                            @foreach ($sandboxModes as $option)
                                <option value="{{ $option['value'] }}">{{ $option['value'] }}</option>
                            @endforeach
                        </select>
                        <p class="codex-hint">{{ $this->selectedDescription($sandboxModes, $sandboxMode) }}</p>

                        <label class="codex-label" for="approvalPolicy">Approval policy</label>
                        <select id="approvalPolicy" wire:model="approvalPolicy" @disabled($fullAuto)>
                            @foreach ($approvalPolicies as $option)
                                <option value="{{ $option['value'] }}">{{ $option['value'] }}</option>
                            @endforeach
                        </select>
                        <p class="codex-hint">{{ $this->selectedDescription($approvalPolicies, $approvalPolicy) }}</p>
                    </section>

                    <details class="codex-log">
                        <summary>CLI Logs (<span>{{ $this->logLineCount() }}</span>)</summary>
                        <div>
                            <pre class="codex-log-stream" wire:stream="log-output">{{ $logStreamText }}</pre>
                        </div>
                    </details>
                </aside>
            </div>
        </section>
    </main>
</div>
