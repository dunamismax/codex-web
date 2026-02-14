<p align="center">
  <img src="public/favicon.svg" alt="Codex Web logo" width="84" />
</p>

<p align="center">
  A Laravel + Livewire web console for streaming Codex CLI runs from your browser.
</p>

# Codex Web

Codex Web is a browser UI for engineers who want Codex CLI workflows without living in the terminal. It streams Codex responses in real time, supports session continuation, and enforces workspace path boundaries server-side.

## Trust Signals

![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6)
![PHPUnit](https://img.shields.io/badge/Tested_with-PHPUnit_11-22C55E)
![License](https://img.shields.io/badge/License-MIT-blue)

## Quick Start

### Prerequisites

- PHP 8.2+
- Composer 2+
- Node.js 20+
- npm 10+
- SQLite 3+ (default) or another Laravel-supported database
- [Codex CLI](https://developers.openai.com/codex/cli) installed and authenticated

### Run

```bash
git clone <your-repo-url> codex-web
cd codex-web
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
composer dev
```

Expected result:

- App is available at `http://localhost:8000`
- Health endpoint responds at `http://localhost:8000/up`
- Home page shows the `Codex Stream Console`

Optional local seed data:

- No demo seed data is currently shipped beyond default Laravel seed scaffolding.

### App Screenshot

<p align="center">
  <img src="public/Codex-Web%20Screenshot%20v2.png" alt="Codex Web application screenshot" width="1200" />
</p>

## Features

- Live chat-style Codex console at `/` with prompt composer, stream controls, and transcript view.
- Real-time SSE stream endpoint at `POST /codex/stream` using `response()->eventStream(...)`.
- Codex thread continuity via `thread.started` handling and `session_id` resume support.
- Configurable runtime options per prompt: working directory, Codex model + reasoning effort, sandbox/approval policies, web search, and additional writable directories.
- Slash-command-style quick actions in the UI for status, session reset, transcript clearing, and fast access to model/permission controls.
- Workspace safety checks that reject `cwd` values outside `CODEX_WORKSPACE_ROOT`.
- Structured stream error handling that emits SSE error events and a deterministic stream completion event.

## Tech Stack

| Layer | Technology | Purpose |
|---|---|---|
| Backend | [Laravel 12](https://laravel.com/docs/12.x) | Routing, validation, streamed responses, app lifecycle |
| Reactive UI | [Livewire 4](https://livewire.laravel.com/) + Alpine | Chat UI state and browser-side stream handling |
| Realtime Transport | [Server-Sent Events](https://laravel.com/docs/12.x/responses#event-streams) | Incremental Codex event delivery |
| Codex Runtime | [Codex CLI](https://developers.openai.com/codex/cli) | Agent execution engine (`exec` / `resume`) |
| Styling / Build | [Tailwind CSS 4](https://tailwindcss.com/) + [Vite 7](https://vite.dev/) | Styling and frontend bundling |
| Data | SQLite (default) | App/session/cache/queue persistence in local development |
| Testing | [PHPUnit 11](https://phpunit.de/) via Laravel test runner | Feature and unit regression coverage |
| Code Style | [Laravel Pint](https://laravel.com/docs/12.x/pint) | Automated PHP formatting |

## Project Structure

```sh
codex-web/
├── app/
│   ├── Http/Controllers/CodexStreamController.php   # Validates stream requests and returns SSE responses
│   ├── Livewire/CodexChat.php                       # Livewire page component config/state bootstrap
│   └── Services/Codex/CodexCliStreamer.php          # Codex CLI process orchestration and JSONL parsing
├── config/codex.php                                 # Codex binary, workspace, model, timeout defaults
├── resources/
│   ├── views/chat.blade.php                         # Main page shell
│   ├── views/livewire/codex-chat.blade.php          # Console UI + client-side SSE parser
│   ├── css/app.css                                  # Tailwind v4 theme and component styling
│   └── js/app.js                                    # Frontend entrypoint
├── routes/web.php                                   # `/` and `POST /codex/stream` routes
├── tests/Feature/ExampleTest.php                    # Stream endpoint and page behavior coverage
├── tests/Unit/ExampleTest.php                       # Baseline unit test
├── composer.json                                    # PHP dependencies and workflow scripts
└── package.json                                     # Frontend scripts and dependencies
```

## Development Workflow and Common Commands

### Setup

```bash
composer setup
```

Alternative explicit setup:

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
```

### Run

```bash
composer dev
```

### Test

```bash
php artisan test --compact
php artisan test --compact tests/Feature/ExampleTest.php
```

### Lint and Format

```bash
vendor/bin/pint --dirty --format agent
```

### Build

```bash
npm run build
```

### Deploy (Generic Laravel Flow)

```bash
php artisan down
npm run build
php artisan migrate --force
php artisan optimize
php artisan up
```

Command verification notes for this README rewrite:

- Verified in this environment: `php artisan --version`, `php artisan route:list --name=codex.stream`, `php artisan route:list --path=up`, `php artisan test --compact tests/Feature/ExampleTest.php`, `npm run build`, `vendor/bin/pint --dirty --format agent`.
- Not executed in this rewrite: `composer dev`, `composer setup`, full production deploy sequence.

## Deployment and Operations

This repository does not include platform-specific deployment manifests (no committed Docker/Kubernetes/Terraform/Caddy config). Deploy it as a standard Laravel application.

- Build assets with `npm run build` before release.
- Run schema updates with `php artisan migrate --force` during deploy.
- Use `GET /up` as a baseline health check endpoint.
- Use `php artisan pail` for live application log tailing.
- Roll back the latest migration batch with `php artisan migrate:rollback` if needed.

## Security and Reliability Notes

- `POST /codex/stream` validates request payloads (`prompt`, `session_id`, `model`, `full_auto`, `cwd`) before execution.
- `cwd` is resolved and constrained to `CODEX_WORKSPACE_ROOT`; out-of-bound paths are rejected with `422` validation errors.
- Stream runtime exceptions are converted to structured SSE error events so clients receive deterministic failure signals.
- Routes are in Laravel's `web` middleware stack, so CSRF protection applies to stream POST requests.
- No authentication or route throttling is configured by default for `/` and `/codex/stream`; run behind trusted access controls before exposing publicly.
- Secrets should be provided via `.env` and must not be committed.

## Documentation

| Path | Purpose |
|---|---|
| [AGENTS.md](AGENTS.md) | Repository-specific coding and tool instructions |
| [config/codex.php](config/codex.php) | Codex runtime configuration surface |
| [routes/web.php](routes/web.php) | Route source of truth for page and stream endpoint |
| [app/Http/Controllers/CodexStreamController.php](app/Http/Controllers/CodexStreamController.php) | SSE controller and workspace path guardrails |
| [app/Services/Codex/CodexCliStreamer.php](app/Services/Codex/CodexCliStreamer.php) | CLI command construction and output event parsing |
| [resources/views/livewire/codex-chat.blade.php](resources/views/livewire/codex-chat.blade.php) | Console UI and frontend stream event handling |
| [tests/Feature/ExampleTest.php](tests/Feature/ExampleTest.php) | Feature tests for stream behavior and validation |
| [boost.json](boost.json) | Laravel Boost MCP configuration |

## Contributing

Contributions are welcome via pull requests.

1. Create a feature branch.
2. Run formatting and tests locally.
3. Open a PR with a clear summary of behavior changes and test impact.

## License

Licensed under the [MIT License](LICENSE).
