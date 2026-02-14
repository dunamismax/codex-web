<?php

namespace App\Http\Controllers;

use App\Services\Codex\CodexCliStreamer;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CodexStreamController extends Controller
{
    public function __invoke(Request $request, CodexCliStreamer $streamer): StreamedResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:120000'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'full_auto' => ['nullable', 'boolean'],
            'cwd' => ['nullable', 'string', 'max:4096'],
        ]);

        $resolvedCwd = $this->validateWorkspacePath($validated['cwd'] ?? null);

        return response()->eventStream(function () use ($streamer, $validated, $resolvedCwd) {
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
                    fullAuto: $validated['full_auto'] ?? (bool) config('codex.default_full_auto', true),
                    cwd: $resolvedCwd,
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

    private function validateWorkspacePath(?string $cwd): string
    {
        $workspaceRoot = realpath((string) config('codex.workspace_root', base_path()));

        if ($workspaceRoot === false) {
            throw ValidationException::withMessages([
                'cwd' => 'Configured workspace root is invalid.',
            ]);
        }

        $target = filled($cwd) ? $cwd : (string) config('codex.default_cwd', base_path());

        if (! $this->isAbsolutePath($target)) {
            $target = $workspaceRoot.DIRECTORY_SEPARATOR.$target;
        }

        $resolved = realpath($target);

        if ($resolved === false || ! is_dir($resolved)) {
            throw ValidationException::withMessages([
                'cwd' => 'Working directory does not exist.',
            ]);
        }

        $workspacePrefix = $workspaceRoot.DIRECTORY_SEPARATOR;

        if ($resolved !== $workspaceRoot && ! str_starts_with($resolved, $workspacePrefix)) {
            throw ValidationException::withMessages([
                'cwd' => 'Working directory must be inside the configured workspace root.',
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
