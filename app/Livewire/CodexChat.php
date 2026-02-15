<?php

namespace App\Livewire;

use App\Services\Codex\CodexCliStreamer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

class CodexChat extends Component
{
    public string $prompt = '';

    public string $workspaceRoot = '';

    public string $workspaceRootSelection = '';

    public string $defaultWorkspaceRoot;

    public string $defaultCwd;

    public ?string $defaultModel;

    public ?string $defaultReasoningEffort;

    public bool $defaultFullAuto;

    public ?string $defaultSandboxMode;

    public ?string $defaultApprovalPolicy;

    public bool $defaultWebSearch;

    /**
     * @var array<int, string>
     */
    public array $workspaceOptions = [];

    public ?string $workspaceParent = null;

    public string $systemRoot = DIRECTORY_SEPARATOR;

    public ?string $homeDirectory = null;

    public string $cwd = '';

    public string $cwdSelection = '';

    /**
     * @var array<int, string>
     */
    public array $cwdSuggestions = [];

    public ?string $cwdParent = null;

    public ?string $model = null;

    public ?string $reasoningEffort = null;

    public bool $fullAuto = false;

    public ?string $sandboxMode = null;

    public ?string $approvalPolicy = null;

    public bool $webSearch = false;

    public string $additionalDirsText = '';

    public ?string $sessionId = null;

    /**
     * @var array<int, array{id: int, role: string, label: string, text: string}>
     */
    public array $messages = [];

    public int $messageCounter = 0;

    public bool $isStreaming = false;

    public string $assistantStreamText = '';

    public string $logStreamText = '';

    /**
     * @var array<int, array{value: string, description: string}>
     */
    public array $models = [];

    /**
     * @var array<int, array{value: string, description: string}>
     */
    public array $reasoningEfforts = [];

    /**
     * @var array<int, array{value: string, description: string}>
     */
    public array $sandboxModes = [];

    /**
     * @var array<int, array{value: string, description: string}>
     */
    public array $approvalPolicies = [];

    public function mount(): void
    {
        $this->defaultWorkspaceRoot = $this->resolvePath((string) config('codex.workspace_root', base_path()));
        $this->defaultCwd = $this->resolvePath((string) config('codex.default_cwd', $this->defaultWorkspaceRoot));

        if (! $this->isWithinRoot($this->defaultCwd, $this->defaultWorkspaceRoot)) {
            $this->defaultCwd = $this->defaultWorkspaceRoot;
        }

        $this->defaultModel = config('codex.default_model');
        $this->defaultReasoningEffort = config('codex.default_reasoning_effort');
        $this->defaultFullAuto = (bool) config('codex.default_full_auto', false);
        $this->defaultSandboxMode = config('codex.default_sandbox_mode');
        $this->defaultApprovalPolicy = config('codex.default_approval_policy');
        $this->defaultWebSearch = (bool) config('codex.default_search', false);

        $this->models = $this->normalizeOptions((array) config('codex.models', []));
        $this->reasoningEfforts = $this->normalizeOptions((array) config('codex.reasoning_efforts', []));
        $this->sandboxModes = $this->normalizeOptions((array) config('codex.sandbox_modes', []));
        $this->approvalPolicies = $this->normalizeOptions((array) config('codex.approval_policies', []));

        $this->model = $this->defaultModel;
        $this->reasoningEffort = $this->defaultReasoningEffort;
        $this->fullAuto = $this->defaultFullAuto;
        $this->sandboxMode = $this->defaultSandboxMode;
        $this->approvalPolicy = $this->defaultApprovalPolicy;
        $this->webSearch = $this->defaultWebSearch;

        $this->refreshWorkspaceBrowser(
            $this->defaultWorkspaceRoot,
            setAsCurrent: true,
            resetCwd: true,
            initialCwd: $this->defaultCwd,
        );

        $this->addMessage(
            'system',
            'System',
            'Ready. Use the right-side controls to configure Codex, then send a prompt.'
        );
    }

    public function render(): View
    {
        return view('livewire.codex-chat');
    }

    public function updatedWorkspaceRootSelection(string $value): void
    {
        if ($value === '__up_workspace__') {
            $this->goUpWorkspace();

            return;
        }

        $this->refreshWorkspaceBrowser($value, setAsCurrent: true, resetCwd: true);
    }

    public function updatedCwdSelection(string $value): void
    {
        if ($value === '__up__') {
            $this->goUpDirectory();

            return;
        }

        $this->refreshCwdSuggestions($value);
    }

    public function goUpWorkspace(): void
    {
        if (! $this->workspaceParent) {
            $this->workspaceRootSelection = $this->workspaceRoot;

            return;
        }

        $this->refreshWorkspaceBrowser($this->workspaceParent, setAsCurrent: true, resetCwd: true);
    }

    public function resetWorkspaceRoot(): void
    {
        $this->refreshWorkspaceBrowser($this->defaultWorkspaceRoot, setAsCurrent: true, resetCwd: true);
    }

    public function goUpDirectory(): void
    {
        if ($this->cwdParent) {
            $this->refreshCwdSuggestions($this->cwdParent);

            return;
        }

        $this->cwdSelection = $this->cwd;
    }

    public function sendPrompt(CodexCliStreamer $streamer): void
    {
        if ($this->isStreaming) {
            return;
        }

        $prompt = trim($this->prompt);

        if ($prompt === '') {
            return;
        }

        $stopSignalKey = $this->stopSignalKey();
        Cache::forget($stopSignalKey);

        $this->isStreaming = true;
        $this->prompt = '';
        $this->assistantStreamText = '';
        $this->logStreamText = '';

        $this->stream(content: '', replace: true, to: 'assistant-output');
        $this->stream(content: '', replace: true, to: 'log-output');
        $this->stream(content: $this->sessionPreviewText(), replace: true, to: 'session-preview');

        $this->addMessage('user', 'You', $prompt);

        $assistantText = '';
        $wasStopped = false;

        try {
            $parsedAdditionalDirectories = $this->parseAdditionalDirectories();

            $this->validateRuntimeOptions(
                prompt: $prompt,
                additionalDirectories: $parsedAdditionalDirectories,
            );

            $resolvedWorkspaceRoot = $this->validateWorkspaceRoot($this->workspaceRoot ?: null);
            $resolvedCwd = $this->validateWorkspacePath(
                cwd: $this->cwd ?: null,
                workspaceRoot: $resolvedWorkspaceRoot,
                field: 'cwd',
                defaultPath: $resolvedWorkspaceRoot,
                doesNotExistMessage: 'Working directory does not exist.',
                outOfBoundsMessage: 'Working directory must be inside the selected workspace root.',
            );
            $resolvedAddDirs = $this->validateAdditionalDirectories(
                directories: $parsedAdditionalDirectories,
                workspaceRoot: $resolvedWorkspaceRoot,
            );

            foreach ($streamer->stream(
                prompt: $prompt,
                sessionId: $this->sessionId,
                model: $this->model ?: config('codex.default_model'),
                reasoningEffort: $this->reasoningEffort ?: config('codex.default_reasoning_effort'),
                fullAuto: $this->fullAuto,
                cwd: $resolvedCwd,
                sandboxMode: $this->sandboxMode ?: config('codex.default_sandbox_mode'),
                approvalPolicy: $this->approvalPolicy ?: config('codex.default_approval_policy'),
                webSearch: $this->webSearch,
                additionalDirectories: $resolvedAddDirs,
            ) as $event) {
                $this->handleCodexEvent($event, $assistantText);

                if ((bool) Cache::pull($stopSignalKey, false)) {
                    $wasStopped = true;
                    break;
                }
            }
        } catch (ValidationException $exception) {
            $firstMessage = collect($exception->errors())
                ->flatten()
                ->first();

            $this->addMessage('error', 'System', is_string($firstMessage) ? $firstMessage : 'Validation failed.');
        } catch (Throwable $exception) {
            report($exception);

            $message = config('app.debug')
                ? $exception->getMessage()
                : 'Codex stream failed unexpectedly.';

            $this->addMessage('error', 'System', $message);
        } finally {
            Cache::forget($stopSignalKey);
            $this->isStreaming = false;
        }

        if ($wasStopped) {
            $this->addMessage('system', 'System', 'Streaming stopped.');
        }

        if (trim($assistantText) !== '') {
            $this->addMessage('assistant', 'Codex', $assistantText);
        }

        $this->assistantStreamText = '';
        $this->stream(content: '', replace: true, to: 'assistant-output');
    }

    public function stopStream(): void
    {
        Cache::put($this->stopSignalKey(), true, now()->addMinutes(5));
    }

    public function resetSession(): void
    {
        if ($this->isStreaming) {
            return;
        }

        $this->sessionId = null;
        $this->messages = [];
        $this->messageCounter = 0;
        $this->assistantStreamText = '';
        $this->logStreamText = '';

        $this->stream(content: $this->sessionPreviewText(), replace: true, to: 'session-preview');
        $this->stream(content: '', replace: true, to: 'assistant-output');
        $this->stream(content: '', replace: true, to: 'log-output');

        $this->addMessage('system', 'System', 'Session reset. Next prompt starts a new Codex thread.');
    }

    public function selectedDescription(array $options, ?string $value): string
    {
        $target = collect($options)->firstWhere('value', $value);

        if (! is_array($target)) {
            return '';
        }

        return (string) ($target['description'] ?? '');
    }

    /**
     * @return array<int, string>
     */
    public function cwdOptions(): array
    {
        $options = $this->cwdSuggestions;

        if ($this->cwd !== '' && ! in_array($this->cwd, $options, true)) {
            array_unshift($options, $this->cwd);
        }

        return array_values(array_unique($options));
    }

    public function canGoUpWorkspace(): bool
    {
        return $this->workspaceParent !== null;
    }

    public function canGoUpDirectory(): bool
    {
        return $this->cwdParent !== null;
    }

    public function workspaceUpLabel(): string
    {
        if (! $this->workspaceParent) {
            return 'Up one level (at top)';
        }

        return sprintf('Up one level (%s)', $this->workspaceParent);
    }

    public function upDirectoryLabel(): string
    {
        if (! $this->cwdParent) {
            return 'Up one level (at workspace root)';
        }

        return sprintf('Up one level (%s)', $this->pathOptionLabel($this->cwdParent));
    }

    public function workspaceOptionLabel(string $path): string
    {
        if ($path === $this->workspaceRoot) {
            return sprintf('Current workspace (%s)', $path);
        }

        if ($this->homeDirectory && $path === $this->homeDirectory) {
            return sprintf('Home (%s)', $path);
        }

        if ($path === $this->systemRoot) {
            return sprintf('System root (%s)', $path);
        }

        return $path;
    }

    public function pathOptionLabel(string $path): string
    {
        $normalizedRoot = $this->normalizePath($this->workspaceRoot);
        $normalizedPath = $this->normalizePath($path);

        if ($normalizedPath === $normalizedRoot) {
            return 'Workspace root';
        }

        if (! str_starts_with($normalizedPath, $normalizedRoot)) {
            return $path;
        }

        $suffix = ltrim(substr($normalizedPath, strlen($normalizedRoot)), '/\\');

        return $suffix === '' ? 'Workspace root' : $suffix;
    }

    public function sessionPreviewText(): string
    {
        if (! $this->sessionId) {
            return 'new';
        }

        return substr($this->sessionId, 0, 8);
    }

    public function logLineCount(): int
    {
        if (trim($this->logStreamText) === '') {
            return 0;
        }

        return count(array_filter(explode("\n", $this->logStreamText), static fn (string $line): bool => trim($line) !== ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleCodexEvent(array $payload, string &$assistantText): void
    {
        $type = $payload['type'] ?? null;

        if (! is_string($type) || $type === '') {
            return;
        }

        if ($type === 'thread.started' && isset($payload['thread_id']) && is_string($payload['thread_id'])) {
            $this->sessionId = $payload['thread_id'];
            $this->stream(content: $this->sessionPreviewText(), replace: true, to: 'session-preview');

            return;
        }

        if (
            $type === 'item.completed'
            && isset($payload['item'])
            && is_array($payload['item'])
            && ($payload['item']['type'] ?? null) === 'agent_message'
        ) {
            $assistantText = (string) ($payload['item']['text'] ?? '');
            $this->assistantStreamText = $assistantText;
            $this->stream(content: $assistantText, replace: true, to: 'assistant-output');

            return;
        }

        if ($type === 'error') {
            $this->addMessage('error', 'Codex', (string) ($payload['message'] ?? 'Unknown stream error.'));

            return;
        }

        if ($type === 'turn.failed') {
            $message = data_get($payload, 'error.message', 'Turn failed.');
            $this->addMessage('error', 'Codex', is_string($message) ? $message : 'Turn failed.');

            return;
        }

        if ($type === 'cli.raw') {
            $stream = (string) ($payload['stream'] ?? 'cli');
            $message = (string) ($payload['message'] ?? '');

            $line = sprintf('[%s] %s', $stream, $message);
            $this->logStreamText = trim($this->logStreamText) === ''
                ? $line
                : $this->logStreamText."\n".$line;

            $this->stream(content: $this->logStreamText, replace: true, to: 'log-output');

            return;
        }

        if ($type === 'process.exited' && isset($payload['exit_code']) && (int) $payload['exit_code'] !== 0) {
            $this->addMessage('error', 'System', sprintf('Codex process exited with code %d.', (int) $payload['exit_code']));
        }
    }

    private function stopSignalKey(): string
    {
        return sprintf('codex-chat:stop:%s:%s', session()->getId(), $this->getId());
    }

    private function refreshWorkspaceBrowser(string $path, bool $setAsCurrent, bool $resetCwd, ?string $initialCwd = null): void
    {
        $listing = $this->directoryListing($path);

        if ($listing === null) {
            $this->addMessage('error', 'System', 'Unable to load directories.');
            $this->workspaceRootSelection = $this->workspaceRoot;

            return;
        }

        $this->systemRoot = $listing['system_root'];
        $this->homeDirectory = $listing['home'];
        $this->workspaceParent = $listing['parent'];
        $this->workspaceOptions = $this->uniquePaths([
            $listing['current'],
            $listing['parent'],
            ...$listing['children'],
            $listing['home'],
            $listing['system_root'],
        ]);

        if ($setAsCurrent) {
            $this->workspaceRoot = $listing['current'];
        }

        $this->workspaceRootSelection = $this->workspaceRoot;

        $targetCwd = $resetCwd
            ? ($initialCwd && $this->pathIsWithinWorkspace($initialCwd) ? $initialCwd : $this->workspaceRoot)
            : ($this->cwd !== '' ? $this->cwd : $this->workspaceRoot);

        $this->refreshCwdSuggestions($targetCwd);
    }

    private function refreshCwdSuggestions(string $path): void
    {
        $listing = $this->directoryListing($path);

        if ($listing === null || ! $this->pathIsWithinWorkspace($listing['current'])) {
            $this->cwd = $this->workspaceRoot;
            $this->cwdSelection = $this->workspaceRoot;
            $this->cwdParent = null;
            $this->cwdSuggestions = [$this->workspaceRoot];

            return;
        }

        $childrenWithinWorkspace = array_values(array_filter(
            $listing['children'],
            fn (string $child): bool => $this->pathIsWithinWorkspace($child)
        ));

        $this->cwd = $listing['current'];
        $this->cwdSelection = $this->cwd;
        $this->cwdParent = $listing['parent'] && $this->pathIsWithinWorkspace($listing['parent'])
            ? $listing['parent']
            : null;
        $this->cwdSuggestions = $this->uniquePaths([
            $listing['current'],
            ...$childrenWithinWorkspace,
        ]);
    }

    /**
     * @return array{current: string, parent: ?string, children: array<int, string>, system_root: string, home: ?string}|null
     */
    private function directoryListing(?string $path): ?array
    {
        $current = $this->resolveDirectory($path);

        if ($current === null) {
            return null;
        }

        return [
            'current' => $current,
            'parent' => $this->parentDirectory($current),
            'children' => $this->childDirectories($current),
            'system_root' => $this->systemRootForPath($current),
            'home' => $this->homeDirectoryPath(),
        ];
    }

    private function resolveDirectory(?string $path): ?string
    {
        $target = filled($path)
            ? $path
            : $this->defaultWorkspaceRoot;

        if (! $this->isAbsolutePath($target)) {
            $target = base_path($target);
        }

        $resolved = realpath($target);

        if ($resolved === false || ! is_dir($resolved)) {
            return null;
        }

        return $resolved;
    }

    private function parentDirectory(string $path): ?string
    {
        $parent = dirname($path);

        return $parent === $path ? null : $parent;
    }

    /**
     * @return array<int, string>
     */
    private function childDirectories(string $path): array
    {
        $entries = @scandir($path);

        if ($entries === false) {
            return [];
        }

        $children = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $candidate = $path.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($candidate)) {
                $children[] = $candidate;
            }
        }

        sort($children);

        return array_slice($children, 0, 250);
    }

    private function systemRootForPath(string $path): string
    {
        if (preg_match('~^[a-zA-Z]:[\\\\/]~', $path) === 1) {
            return strtoupper(substr($path, 0, 1)).':\\';
        }

        return DIRECTORY_SEPARATOR;
    }

    private function homeDirectoryPath(): ?string
    {
        $home = getenv('HOME');

        if (! is_string($home) || $home === '') {
            return null;
        }

        $resolved = realpath($home);

        if ($resolved === false || ! is_dir($resolved)) {
            return null;
        }

        return $resolved;
    }

    /**
     * @param  array<int, string>  $additionalDirectories
     */
    private function validateRuntimeOptions(string $prompt, array $additionalDirectories): void
    {
        Validator::make(
            [
                'prompt' => $prompt,
                'workspace_root' => $this->workspaceRoot,
                'cwd' => $this->cwd,
                'model' => $this->model,
                'reasoning_effort' => $this->reasoningEffort,
                'full_auto' => $this->fullAuto,
                'sandbox_mode' => $this->sandboxMode,
                'approval_policy' => $this->approvalPolicy,
                'web_search' => $this->webSearch,
                'add_dirs' => $additionalDirectories,
            ],
            [
                'prompt' => ['required', 'string', 'max:120000'],
                'workspace_root' => ['nullable', 'string', 'max:4096'],
                'cwd' => ['nullable', 'string', 'max:4096'],
                'model' => ['nullable', 'string', Rule::in(array_keys((array) config('codex.models', [])))],
                'reasoning_effort' => ['nullable', 'string', Rule::in(array_keys((array) config('codex.reasoning_efforts', [])))],
                'full_auto' => ['required', 'boolean'],
                'sandbox_mode' => ['nullable', 'string', Rule::in(array_keys((array) config('codex.sandbox_modes', [])))],
                'approval_policy' => ['nullable', 'string', Rule::in(array_keys((array) config('codex.approval_policies', [])))],
                'web_search' => ['required', 'boolean'],
                'add_dirs' => ['nullable', 'array', 'max:8'],
                'add_dirs.*' => ['string', 'max:4096'],
            ],
            [
                'model.in' => 'The selected model is not supported by this Codex Web build.',
                'reasoning_effort.in' => 'The selected reasoning effort is invalid.',
                'sandbox_mode.in' => 'The selected sandbox mode is invalid.',
                'approval_policy.in' => 'The selected approval policy is invalid.',
            ]
        )->validate();
    }

    /**
     * @param  array<int, string>  $directories
     * @return array<int, string>
     */
    private function validateAdditionalDirectories(array $directories, string $workspaceRoot): array
    {
        $resolved = [];

        foreach ($directories as $index => $directory) {
            $resolved[] = $this->validateWorkspacePath(
                cwd: $directory,
                workspaceRoot: $workspaceRoot,
                field: "add_dirs.$index",
                defaultPath: null,
                doesNotExistMessage: 'Additional writable directory does not exist.',
                outOfBoundsMessage: 'Additional writable directories must be inside the selected workspace root.',
            );
        }

        return array_values(array_unique($resolved));
    }

    private function validateWorkspaceRoot(?string $workspaceRoot): string
    {
        $target = filled($workspaceRoot)
            ? $workspaceRoot
            : $this->defaultWorkspaceRoot;

        if (! $this->isAbsolutePath($target)) {
            $target = base_path($target);
        }

        $resolved = realpath($target);

        if ($resolved === false || ! is_dir($resolved)) {
            throw ValidationException::withMessages([
                'workspace_root' => 'Workspace root does not exist.',
            ]);
        }

        return $resolved;
    }

    private function validateWorkspacePath(
        ?string $cwd,
        string $workspaceRoot,
        string $field,
        ?string $defaultPath,
        string $doesNotExistMessage,
        string $outOfBoundsMessage,
    ): string {
        $target = filled($cwd) ? $cwd : $defaultPath;

        if (blank($target)) {
            throw ValidationException::withMessages([
                $field => $doesNotExistMessage,
            ]);
        }

        if (! $this->isAbsolutePath($target)) {
            $target = $workspaceRoot.DIRECTORY_SEPARATOR.$target;
        }

        $resolved = realpath($target);

        if ($resolved === false || ! is_dir($resolved)) {
            throw ValidationException::withMessages([
                $field => $doesNotExistMessage,
            ]);
        }

        $workspacePrefix = $workspaceRoot.DIRECTORY_SEPARATOR;

        if ($resolved !== $workspaceRoot && ! str_starts_with($resolved, $workspacePrefix)) {
            throw ValidationException::withMessages([
                $field => $outOfBoundsMessage,
            ]);
        }

        return $resolved;
    }

    /**
     * @return array<int, string>
     */
    private function parseAdditionalDirectories(): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $this->additionalDirsText);

        if (! is_array($lines)) {
            return [];
        }

        $normalized = [];

        foreach ($lines as $line) {
            $candidate = trim($line);

            if ($candidate === '' || $candidate === $this->cwd) {
                continue;
            }

            $normalized[] = $candidate;
        }

        return array_values(array_unique($normalized));
    }

    private function addMessage(string $role, string $label, string $text): void
    {
        $this->messageCounter++;

        $this->messages[] = [
            'id' => $this->messageCounter,
            'role' => $role,
            'label' => $label,
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, string>  $options
     * @return array<int, array{value: string, description: string}>
     */
    private function normalizeOptions(array $options): array
    {
        $result = [];

        foreach ($options as $value => $description) {
            $result[] = [
                'value' => $value,
                'description' => $description,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, string|null>  $paths
     * @return array<int, string>
     */
    private function uniquePaths(array $paths): array
    {
        $unique = [];
        $seen = [];

        foreach ($paths as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            if (isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;
            $unique[] = $path;
        }

        return $unique;
    }

    private function pathIsWithinWorkspace(string $path): bool
    {
        $normalizedPath = $this->normalizePath($path);
        $normalizedRoot = $this->normalizePath($this->workspaceRoot);

        if ($normalizedPath === '' || $normalizedRoot === '') {
            return false;
        }

        if ($normalizedPath === $normalizedRoot) {
            return true;
        }

        if ($normalizedRoot === '/' || preg_match('~^[a-zA-Z]:[\\\\/]$~', $normalizedRoot) === 1) {
            return str_starts_with($normalizedPath, $normalizedRoot);
        }

        $separator = str_contains($normalizedRoot, '\\') ? '\\' : '/';

        return str_starts_with($normalizedPath, $normalizedRoot.$separator);
    }

    private function normalizePath(string $path): string
    {
        if ($path === '/' || preg_match('~^[a-zA-Z]:[\\\\/]$~', $path) === 1) {
            return $path;
        }

        return rtrim($path, '\\/');
    }

    private function resolvePath(string $path): string
    {
        if (! $this->isAbsolutePath($path)) {
            $path = base_path($path);
        }

        return realpath($path) ?: base_path();
    }

    private function isWithinRoot(string $path, string $root): bool
    {
        if ($path === $root) {
            return true;
        }

        return str_starts_with($path, $root.DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '\\\\')
            || preg_match('~^[a-zA-Z]:[\\\\/]~', $path) === 1;
    }
}
