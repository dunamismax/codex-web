# Codex Web (Laravel + Livewire)

A Laravel 12 + Livewire app that streams Codex CLI output into a web chat UI.

## What this builds

- Live chat-style web interface (`/`) with a custom UI
- Backend SSE endpoint that runs Codex CLI in real time
- JSONL event parsing from `codex exec --json`
- Session continuity using Codex `thread_id` + `codex exec resume`
- Configurable working directory, model, and `--full-auto`

## Stack

- Laravel 12
- Livewire 4 (class-based component)
- Symfony Process (via Laravel) for Codex process orchestration
- Server-Sent Events (`response()->eventStream`) for streaming

## Run locally

1. Install deps:

```bash
composer install
npm install
```

2. Configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

3. Optional Codex settings in `.env`:

```dotenv
CODEX_BINARY=codex
CODEX_WORKSPACE_ROOT=/absolute/path/allowed-for-codex
CODEX_DEFAULT_CWD=/absolute/path/allowed-for-codex
CODEX_DEFAULT_MODEL=
CODEX_DEFAULT_FULL_AUTO=true
CODEX_SKIP_GIT_REPO_CHECK=true
CODEX_PROCESS_TIMEOUT=1800
```

4. Start app:

```bash
npm run dev
php artisan serve
```

5. Open:

`http://127.0.0.1:8000`

## Key files

- `app/Http/Controllers/CodexStreamController.php`: validates request, enforces workspace path guard, returns SSE stream
- `app/Services/Codex/CodexCliStreamer.php`: runs Codex CLI, parses stdout/stderr JSONL events
- `app/Livewire/CodexChat.php`: Livewire page component
- `resources/views/livewire/codex-chat.blade.php`: frontend UI + stream parser logic
- `config/codex.php`: Codex integration config

## Notes

- This implementation uses `codex exec` / `codex exec resume` for robust CLI compatibility and stream parsing.
- Codex app-server support exists and can be added later for deeper protocol-level parity.
- Codex authentication/network access must already work in the environment where this app runs.

## Research references used

- Codex docs overview: https://developers.openai.com/codex/overview
- Codex CLI docs: https://developers.openai.com/codex/cli
- Codex non-interactive mode (`exec --json`, `resume`): https://developers.openai.com/codex/cli/non-interactive
- Codex app-server docs: https://developers.openai.com/codex/app-server
- Laravel streamed responses / SSE (`stream`, `eventStream`): https://laravel.com/docs/12.x/responses
- Livewire docs (`wire:stream`, JS/event integration): https://livewire.laravel.com
