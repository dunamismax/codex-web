<p align="center">
  <img src="public/favicon.svg" alt="Codex Web logo" width="84" />
</p>

<p align="center">
  Bun + React Router framework-mode SPA for streaming Codex CLI runs from your browser.
</p>

# Codex Web

Codex Web is a Bun-first TypeScript app with a React Router frontend and Bun API server. It streams Codex responses in real time, supports thread resume, and enforces workspace path boundaries on the server.

## Stack

- Runtime and package manager: Bun
- Frontend: Vite + React Router (framework mode, `ssr: false`) + React 19.2 + TypeScript
- UI: Tailwind CSS v4 + shadcn-style component primitives
- Data: Postgres + Drizzle ORM + drizzle-kit
- Validation: Zod
- Formatting/linting: Biome

## Features

- Live streaming console at `/` using SSE from `POST /api/codex/stream`
- Session continuation using Codex thread IDs (`exec` + `exec resume`)
- Runtime controls in the UI:
  - workspace root
  - cwd
  - additional writable directories (`--add-dir`)
  - model
  - reasoning effort
  - full-auto
  - sandbox mode
  - approval policy
  - live web search
- Directory browser API at `GET /api/codex/directories`
- Server-side path safety: `cwd` and `add_dirs` must resolve inside selected workspace root

## Requirements

- Bun 1.2+
- Codex CLI installed and authenticated
- Postgres 15+ (if using Drizzle-backed persistence)

## Quick Start

```bash
cp .env.example .env
bun install
bun run build
bun run start
```

Dev workflow (run in two terminals):

```bash
# terminal 1: Bun API server
bun run dev

# terminal 2: React Router + Vite dev app
bun run dev:app
```

Endpoints:

- App (dev): `http://localhost:5173`
- API server: `http://localhost:8787`

## Commands

```bash
bun run lint
bun run typecheck
bun run build
bun run scry:doctor

bun run db:generate
bun run db:migrate
```

## API

- `GET /api/codex/config`: runtime defaults + option lists
- `GET /api/codex/directories?path=/abs/or/relative`: directory listing payload
- `POST /api/codex/stream`: SSE stream of Codex events

## Notes

- `Auth.js` is not wired yet because the current app is intentionally unauthenticated, matching previous behavior.
- Static production assets are served from `build/client` by the Bun server.

## License

MIT (`LICENSE`)
