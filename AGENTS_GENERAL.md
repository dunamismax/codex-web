# AGENTS General Laravel Template

Use this file as a starting point for new Laravel projects. Customize the `Project Profile` section first.

<laravel-general-guidelines>
=== project profile ===

# Project Profile (Edit First)

- App name: `<your-app-name>`
- Laravel version: `<e.g. 12.x>`
- PHP version: `<e.g. 8.3+>`
- Primary UI stack: `<Blade | Livewire | Inertia | API-only>`
- CSS stack: `<Tailwind | Bootstrap | Custom>`
- Test framework: `PHPUnit`
- Database engine: `<MySQL | PostgreSQL | SQLite>`
- Queue driver: `<redis | database | sqs>`
- Cache driver: `<redis | database | file>`

=== core principles ===

# Core Principles

- Keep changes small, concrete, and verifiable.
- Follow existing project conventions before introducing new patterns.
- Prefer Laravel-native approaches before custom frameworks.
- Optimize for maintainability over novelty.
- Avoid hidden behavior and implicit coupling.

=== workflow ===

# Default Workflow

1. Read existing code paths and conventions before editing.
2. Confirm the route, controller/component, request validation, and data flow.
3. Implement the smallest correct change.
4. Run formatting and the minimum relevant tests.
5. Update docs only when behavior or usage changed.
6. Report what changed, why, and how it was verified.

=== architecture ===

# Application Architecture

- Do not create new top-level folders without clear need.
- Keep controllers thin and move business logic into actions/services.
- Use Form Requests for validation instead of inline controller validation.
- Use Policies/Gates for authorization.
- Use Jobs for slow or retryable work.
- Use Events/Listeners for side effects, not core transactional logic.
- Use named routes and `route()` for URL generation.

=== laravel conventions ===

# Laravel Conventions

- Use `php artisan make:*` commands for framework artifacts.
- Pass `--no-interaction` to Artisan commands in automation.
- Use explicit return types and parameter types in PHP methods.
- Use constructor property promotion where appropriate.
- Use `config()` values in app code; do not call `env()` outside config files.
- Keep middleware, exception, and routing setup aligned with current Laravel structure.

=== eloquent and database ===

# Database and Eloquent

- Prefer Eloquent models and relationships over raw queries.
- Add relationship return types on model methods.
- Prevent N+1 queries using eager loading.
- Use query scopes for reusable query fragments.
- Use migrations for all schema changes; never mutate schema manually.
- When altering columns, preserve all existing attributes intentionally.
- Add indexes for high-traffic filters and joins.
- Create factories for domain models and use them in tests.

=== api ===

# API Guidelines

- Prefer API Resources for output shaping.
- Validate all input with Form Requests.
- Use versioned API routes for public/stable APIs.
- Return consistent error payloads and HTTP status codes.
- Paginate large collections.

=== livewire and frontend ===

# Frontend and UX

- Preserve existing design system patterns when present.
- Build reusable UI components before duplicating markup.
- Prefer semantic HTML and accessible controls.
- Ensure keyboard access and visible focus states.
- Keep color contrast readable and responsive layouts reliable.
- For Livewire: keep state server-driven, validate in actions, and avoid unnecessary custom JS.
- For Tailwind: use project tokens/utilities consistently and avoid one-off visual drift.

=== security ===

# Security Baseline

- Validate and authorize every mutation endpoint.
- Keep CSRF protection enabled for web routes.
- Escape untrusted output by default.
- Restrict file uploads by type, size, and storage location.
- Never log secrets, tokens, or sensitive personal data.
- Keep `.env` values out of source control and out of responses.
- Apply rate limiting to sensitive endpoints.

=== testing ===

# Testing Standards

- Write tests as PHPUnit classes.
- Prefer feature tests for HTTP behavior and integration paths.
- Add unit tests for isolated domain logic.
- Cover happy path, failure path, and edge cases.
- Do not delete existing tests without explicit approval.
- Run targeted tests first, then run broader suite when needed.

=== performance ===

# Performance and Reliability

- Profile query counts for list/detail screens.
- Cache expensive, stable reads with clear invalidation strategy.
- Use queues for expensive or external operations.
- Add timeouts/retries/circuit behavior for external services.
- Keep idempotency in mind for jobs and webhooks.

=== observability ===

# Logging and Diagnostics

- Log meaningful context for failures.
- Use consistent log channels/levels by environment.
- Prefer structured logs for machine parsing.
- Surface actionable error messages to users, hide sensitive internals in production.

=== docs and maintenance ===

# Documentation Rules

- Keep `README.md` accurate with commands, routes, and runtime options.
- Keep `AGENTS.md` aligned with actual project behavior and constraints.
- Update docs in the same change when behavior changes.
- Do not add new documentation files unless requested.

=== quality gates ===

# Quality Gates Before Finalizing

- `vendor/bin/pint --dirty --format agent` or project formatter equivalent
- `php artisan test --compact` for full suite (or targeted subset when scoped)
- Frontend build check when UI assets changed: `npm run build`
- Confirm no debug code, dumps, or temporary logs remain

=== code review mode ===

# Code Review Expectations

- Prioritize findings by severity.
- Focus on correctness, regressions, security, and missing test coverage.
- Provide file and line references for each issue.
- Keep summaries brief after findings.

=== collaboration style ===

# Communication Style

- Be direct, concise, and factual.
- State assumptions explicitly.
- Explain tradeoffs when there is more than one valid approach.
- Provide clear next steps when useful.

=== quick command reference ===

# Common Commands

```bash
php artisan test --compact
php artisan test --compact tests/Feature/SomeFeatureTest.php
php artisan test --compact --filter=testName
vendor/bin/pint --dirty --format agent
npm run build
php artisan migrate --no-interaction
php artisan queue:work
```

</laravel-general-guidelines>
