<?php

namespace App\Http\Controllers;

use App\Http\Requests\CodexStreamRequest;
use App\Services\Codex\CodexCliStreamer;
use Illuminate\Http\StreamedEvent;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CodexStreamController extends Controller
{
    public function __invoke(CodexStreamRequest $request, CodexCliStreamer $streamer): StreamedResponse
    {
        /** @var array{
         * prompt: string,
         * session_id?: string|null,
         * model?: string|null,
         * reasoning_effort?: string|null,
         * full_auto?: bool|null,
         * cwd?: string|null,
         * sandbox_mode?: string|null,
         * approval_policy?: string|null,
         * web_search?: bool|null,
         * add_dirs?: array<int, string>|null
         * } $validated
         */
        $validated = $request->validated();

        $resolvedCwd = $this->validateWorkspacePath(
            cwd: $validated['cwd'] ?? null,
            field: 'cwd',
            defaultToConfigured: true,
            doesNotExistMessage: 'Working directory does not exist.',
            outOfBoundsMessage: 'Working directory must be inside the configured workspace root.',
        );
        $resolvedAddDirs = $this->validateAdditionalDirectories($validated['add_dirs'] ?? []);

        return response()->eventStream(function () use ($streamer, $validated, $resolvedCwd, $resolvedAddDirs) {
            yield new StreamedEvent(
                event: 'meta',
                data: [
                    'type' => 'stream.started',
                    'session_id' => $validated['session_id'] ?? null,
                ],
            );

            try {
                foreach ($streamer->stream(
                    prompt: $validated['prompt'],
                    sessionId: $validated['session_id'] ?? null,
                    model: $validated['model'] ?? config('codex.default_model'),
                    reasoningEffort: $validated['reasoning_effort'] ?? config('codex.default_reasoning_effort'),
                    fullAuto: $validated['full_auto'] ?? (bool) config('codex.default_full_auto', false),
                    cwd: $resolvedCwd,
                    sandboxMode: $validated['sandbox_mode'] ?? config('codex.default_sandbox_mode'),
                    approvalPolicy: $validated['approval_policy'] ?? config('codex.default_approval_policy'),
                    webSearch: $validated['web_search'] ?? (bool) config('codex.default_search', false),
                    additionalDirectories: $resolvedAddDirs,
                ) as $event) {
                    yield new StreamedEvent(event: 'codex', data: $event);
                }
            } catch (Throwable $exception) {
                report($exception);

                $message = config('app.debug')
                    ? $exception->getMessage()
                    : 'Codex stream failed unexpectedly.';

                yield new StreamedEvent(
                    event: 'codex',
                    data: [
                        'type' => 'error',
                        'message' => $message,
                    ],
                );
            } finally {
                yield new StreamedEvent(
                    event: 'done',
                    data: ['type' => 'stream.finished'],
                );
            }
        }, endStreamWith: null);
    }

    /**
     * @param  array<int, string>  $directories
     * @return array<int, string>
     */
    private function validateAdditionalDirectories(array $directories): array
    {
        $resolved = [];

        foreach ($directories as $index => $directory) {
            $resolved[] = $this->validateWorkspacePath(
                cwd: $directory,
                field: "add_dirs.$index",
                defaultToConfigured: false,
                doesNotExistMessage: 'Additional writable directory does not exist.',
                outOfBoundsMessage: 'Additional writable directories must be inside the configured workspace root.',
            );
        }

        return array_values(array_unique($resolved));
    }

    private function validateWorkspacePath(
        ?string $cwd,
        string $field,
        bool $defaultToConfigured,
        string $doesNotExistMessage,
        string $outOfBoundsMessage
    ): string {
        $workspaceRoot = realpath((string) config('codex.workspace_root', base_path()));

        if ($workspaceRoot === false) {
            throw ValidationException::withMessages([
                $field => 'Configured workspace root is invalid.',
            ]);
        }

        $target = filled($cwd)
            ? $cwd
            : ($defaultToConfigured ? (string) config('codex.default_cwd', base_path()) : null);

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

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '\\\\')
            || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) === 1;
    }
}
