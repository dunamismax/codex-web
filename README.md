<p align="center">
  <img src="public/favicon.svg" alt="Codex Web logo" width="84" />
</p>

<p align="center">
  A Laravel + Livewire + Alpine + Flux UI web console for streaming Codex CLI runs from your browser.
</p>

# Codex Web

Codex Web is a browser UI for Codex CLI workflows. It streams Codex responses in real time, supports multi-turn sessions, and enforces workspace path boundaries on the server.

## Highlights

- Real-time streaming chat UI at `/` backed by `POST /codex/stream` SSE.
- Session continuation using Codex thread IDs (`exec` + `exec resume`).
- Flux UI integration for shared, accessible UI primitives in the chat console.
- Configurable runtime controls in the UI:
  - Workspace location (can be moved anywhere on the system)
  - Working directory
  - Additional writable directories
  - Model
  - Reasoning effort
  - `--full-auto`
  - Sandbox mode
  - Approval policy
  - Live web search
- Server-side path safety: `cwd` / `add_dirs` must resolve inside the selected `workspace_root`.
- Responsive viewport behavior with internal panel scrolling on large screens.

## Frontend Stack

- Laravel + Livewire (server-driven application and component rendering)
- Alpine.js (lightweight client-side interactions inside Livewire views)
- Flux UI (official Livewire component library; free tier components in use)
- Tailwind CSS v4 (styling foundation, including Flux styles)

## Runtime Option Support (Current)

Codex Web validates and accepts these request fields:

- `prompt`, `session_id`, `workspace_root`, `model`, `reasoning_effort`, `full_auto`, `cwd`, `sandbox_mode`, `approval_policy`, `web_search`, `add_dirs`

Current Codex CLI forwarding behavior in this app:

- Forwarded on new turns (`codex exec`):
  - `model` (`--model`)
  - `reasoning_effort` (`--config model_reasoning_effort=...`)
  - `full_auto` (`--full-auto`)
  - `sandbox_mode` (`--sandbox`) when not full-auto
  - `approval_policy` (`--ask-for-approval`) when not full-auto
  - `web_search` (`--search`)
  - `cwd` (`--cd`)
  - `add_dirs` (`--add-dir`)
- Forwarded on resumed turns (`codex exec resume`):
  - `model`, `reasoning_effort`, `full_auto`, `approval_policy`, `web_search`
  - `session_id` (resume target)

## Requirements

- PHP 8.2+
- Composer 2+
- Node.js 20+
- npm 10+
- SQLite 3+ (default) or another Laravel-supported database
- [Codex CLI](https://developers.openai.com/codex/cli) installed and authenticated

## Quick Start

### One-command setup

```bash
composer setup
```

### Manual setup

```bash
git clone <your-repo-url> codex-web
cd codex-web
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
npm run build
```

### Run locally

```bash
composer dev
```

Expected local endpoints:

- App: `http://localhost:8000`
- Health check: `http://localhost:8000/up`

## App Screenshot

<p align="center">
  <img src="public/Codex-Web%20Screenshot%20v4.png" alt="Codex Web application screenshot" width="1200" />
</p>

## Configuration

Codex runtime settings live in `config/codex.php` (overridable via `.env`):

- `CODEX_BINARY`
- `CODEX_WORKSPACE_ROOT`
- `CODEX_DEFAULT_CWD`
- `CODEX_DEFAULT_MODEL`
- `CODEX_DEFAULT_REASONING_EFFORT`
- `CODEX_DEFAULT_FULL_AUTO`
- `CODEX_DEFAULT_SANDBOX_MODE`
- `CODEX_DEFAULT_APPROVAL_POLICY`
- `CODEX_DEFAULT_SEARCH`
- `CODEX_SKIP_GIT_REPO_CHECK`
- `CODEX_PROCESS_TIMEOUT`

## Common Commands

### Run tests

```bash
php artisan test --compact
php artisan test --compact tests/Feature/ExampleTest.php
```

### Format code

```bash
vendor/bin/pint --dirty --format agent
```

### Build frontend assets

```bash
npm run build
```

## Security Notes

- Stream requests are validated by `app/Http/Requests/CodexStreamRequest.php`.
- `workspace_root` is validated as an existing directory.
- `cwd` and `add_dirs` are resolved and constrained to the selected `workspace_root`.
- Stream failures are converted into structured SSE error events.
- Routes use Laravel `web` middleware (CSRF protection included).
- `/`, `/codex/stream`, and `/codex/directories` are unauthenticated by default; protect access before public exposure.

## Project Structure

```sh
codex-web/
├── app/
│   ├── Http/Controllers/CodexStreamController.php
│   ├── Http/Controllers/CodexDirectoryController.php
│   ├── Http/Requests/CodexStreamRequest.php
│   ├── Livewire/CodexChat.php
│   └── Services/Codex/CodexCliStreamer.php
├── config/codex.php
├── resources/
│   ├── views/chat.blade.php
│   ├── views/livewire/codex-chat.blade.php
│   ├── css/app.css
│   └── js/app.js
├── routes/web.php
├── tests/Feature/ExampleTest.php
├── composer.json
└── package.json
```

## License

Licensed under the [MIT License](LICENSE).
