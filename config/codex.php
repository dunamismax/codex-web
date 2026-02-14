<?php

return [
    'binary' => env('CODEX_BINARY', 'codex'),
    'workspace_root' => env('CODEX_WORKSPACE_ROOT', base_path()),
    'default_cwd' => env('CODEX_DEFAULT_CWD', base_path()),
    'models' => [
        'gpt-5.3-codex' => 'Latest frontier agentic coding model.',
        'gpt-5.2-codex' => 'Frontier agentic coding model.',
        'gpt-5.1-codex-max' => 'Codex-optimized flagship for deep and fast reasoning.',
        'gpt-5.2' => 'Latest frontier model with improvements across knowledge, reasoning and coding.',
        'gpt-5.1-codex-mini' => 'Optimized for codex. Cheaper, faster, but less capable.',
    ],
    'reasoning_efforts' => [
        'low' => 'Fast responses with lighter reasoning.',
        'medium' => 'Balances speed and reasoning depth for everyday tasks.',
        'high' => 'Greater reasoning depth for complex problems.',
        'xhigh' => 'Extra high reasoning depth for complex problems.',
    ],
    'sandbox_modes' => [
        'read-only' => 'Read files without writing.',
        'workspace-write' => 'Write changes in the workspace.',
        'danger-full-access' => 'No sandbox restrictions (use with caution).',
    ],
    'approval_policies' => [
        'untrusted' => 'Only trusted commands run without approval.',
        'on-failure' => 'Ask for approval only if a command fails.',
        'on-request' => 'Model asks for approval when needed.',
        'never' => 'Never ask for approval.',
    ],
    'default_model' => env('CODEX_DEFAULT_MODEL', 'gpt-5.3-codex'),
    'default_reasoning_effort' => env('CODEX_DEFAULT_REASONING_EFFORT', 'high'),
    'default_full_auto' => env('CODEX_DEFAULT_FULL_AUTO', false),
    'default_sandbox_mode' => env('CODEX_DEFAULT_SANDBOX_MODE', 'workspace-write'),
    'default_approval_policy' => env('CODEX_DEFAULT_APPROVAL_POLICY', 'on-request'),
    'default_search' => env('CODEX_DEFAULT_SEARCH', false),
    'skip_git_repo_check' => env('CODEX_SKIP_GIT_REPO_CHECK', true),
    'process_timeout' => (int) env('CODEX_PROCESS_TIMEOUT', 1800),
];
