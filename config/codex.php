<?php

return [
    'binary' => env('CODEX_BINARY', 'codex'),
    'workspace_root' => env('CODEX_WORKSPACE_ROOT', base_path()),
    'default_cwd' => env('CODEX_DEFAULT_CWD', base_path()),
    'default_model' => env('CODEX_DEFAULT_MODEL'),
    'default_full_auto' => env('CODEX_DEFAULT_FULL_AUTO', true),
    'skip_git_repo_check' => env('CODEX_SKIP_GIT_REPO_CHECK', true),
    'process_timeout' => (int) env('CODEX_PROCESS_TIMEOUT', 1800),
];
