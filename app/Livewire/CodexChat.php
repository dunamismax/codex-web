<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class CodexChat extends Component
{
    public string $streamUrl;

    public string $workspaceRoot;

    public string $defaultCwd;

    public ?string $defaultModel;

    public ?string $defaultReasoningEffort;

    public bool $defaultFullAuto;

    public ?string $defaultSandboxMode;

    public ?string $defaultApprovalPolicy;

    public bool $defaultWebSearch;

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

    /**
     * @var array<int, string>
     */
    public array $cwdSuggestions = [];

    public function mount(): void
    {
        $this->streamUrl = route('codex.stream');
        $this->workspaceRoot = $this->resolvePath((string) config('codex.workspace_root', base_path()));
        $this->defaultCwd = $this->resolvePath((string) config('codex.default_cwd', $this->workspaceRoot));
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
        $this->cwdSuggestions = $this->discoverWorkspaceDirectories($this->workspaceRoot);
    }

    public function render(): View
    {
        return view('livewire.codex-chat');
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

    private function resolvePath(string $path): string
    {
        if (! $this->isAbsolutePath($path)) {
            $path = base_path($path);
        }

        return realpath($path) ?: base_path();
    }

    /**
     * @return array<int, string>
     */
    private function discoverWorkspaceDirectories(string $workspaceRoot): array
    {
        $directories = [$workspaceRoot];
        $entries = @scandir($workspaceRoot);

        if ($entries === false) {
            return $directories;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $candidate = $workspaceRoot.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($candidate)) {
                $directories[] = $candidate;
            }
        }

        sort($directories);

        return array_slice($directories, 0, 75);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '\\\\')
            || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) === 1;
    }
}
