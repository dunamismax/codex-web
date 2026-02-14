<?php

namespace App\Services\Codex;

use Generator;
use SplQueue;
use Symfony\Component\Process\Process;

class CodexCliStreamer
{
    /**
     * @param  array<int, string>  $additionalDirectories
     */
    public function stream(
        string $prompt,
        ?string $sessionId = null,
        ?string $model = null,
        ?string $reasoningEffort = null,
        bool $fullAuto = false,
        ?string $cwd = null,
        ?string $sandboxMode = null,
        ?string $approvalPolicy = null,
        bool $webSearch = false,
        array $additionalDirectories = []
    ): Generator {
        $command = $this->buildCommand(
            sessionId: $sessionId,
            model: $model,
            reasoningEffort: $reasoningEffort,
            fullAuto: $fullAuto,
            cwd: $cwd,
            sandboxMode: $sandboxMode,
            approvalPolicy: $approvalPolicy,
            webSearch: $webSearch,
            additionalDirectories: $additionalDirectories,
        );

        $process = new Process($command, $this->resolveWorkingDirectory($cwd));
        $process->setTimeout((float) config('codex.process_timeout'));
        $process->setIdleTimeout(null);
        $process->setInput($prompt);

        /** @var SplQueue<array<string, mixed>> $events */
        $events = new SplQueue;
        $buffers = [
            Process::OUT => '',
            Process::ERR => '',
        ];

        $enqueue = static function (array $event) use ($events): void {
            $events->enqueue($event);
        };

        $process->start(function (string $type, string $chunk) use (&$buffers, $enqueue): void {
            $buffers[$type] .= $chunk;
            $this->drainBuffer($buffers[$type], $type === Process::OUT ? 'stdout' : 'stderr', $enqueue);
        });

        try {
            while ($process->isRunning() || ! $events->isEmpty()) {
                while (! $events->isEmpty()) {
                    yield $events->dequeue();
                }

                usleep(15_000);
            }

            $this->flushBuffer($buffers[Process::OUT], 'stdout', $enqueue);
            $this->flushBuffer($buffers[Process::ERR], 'stderr', $enqueue);

            while (! $events->isEmpty()) {
                yield $events->dequeue();
            }

            yield [
                'type' => 'process.exited',
                'exit_code' => $process->getExitCode(),
            ];
        } finally {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
    }

    /**
     * @param  array<int, string>  $additionalDirectories
     * @return array<int, string>
     */
    private function buildCommand(
        ?string $sessionId,
        ?string $model,
        ?string $reasoningEffort,
        bool $fullAuto,
        ?string $cwd,
        ?string $sandboxMode,
        ?string $approvalPolicy,
        bool $webSearch,
        array $additionalDirectories
    ): array {
        $isResuming = filled($sessionId);
        $command = [config('codex.binary')];

        if (! $fullAuto && filled($approvalPolicy)) {
            $command[] = '--ask-for-approval';
            $command[] = $approvalPolicy;
        }

        if ($webSearch) {
            $command[] = '--search';
        }

        $command[] = 'exec';

        if ($isResuming) {
            $command[] = 'resume';
        }

        $command[] = '--json';

        if ((bool) config('codex.skip_git_repo_check', true)) {
            $command[] = '--skip-git-repo-check';
        }

        if ($fullAuto) {
            $command[] = '--full-auto';
        } elseif (! $isResuming) {
            if (filled($sandboxMode)) {
                $command[] = '--sandbox';
                $command[] = $sandboxMode;
            }
        }

        if (filled($model)) {
            $command[] = '--model';
            $command[] = $model;
        }

        if (filled($reasoningEffort)) {
            $command[] = '--config';
            $command[] = sprintf('model_reasoning_effort="%s"', $reasoningEffort);
        }

        if (! $isResuming && filled($cwd)) {
            $command[] = '--cd';
            $command[] = $this->resolveWorkingDirectory($cwd);
        }

        if (! $isResuming) {
            foreach ($additionalDirectories as $directory) {
                $command[] = '--add-dir';
                $command[] = $this->resolveWorkingDirectory($directory);
            }
        }

        if ($isResuming) {
            $command[] = $sessionId;
        }

        $command[] = '-';

        return $command;
    }

    private function resolveWorkingDirectory(?string $cwd): string
    {
        $candidate = filled($cwd) ? $cwd : config('codex.default_cwd', base_path());

        if (! $this->isAbsolutePath($candidate)) {
            $candidate = base_path($candidate);
        }

        return realpath($candidate) ?: base_path();
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '\\\\')
            || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * @param  callable(array<string, mixed>): void  $enqueue
     */
    private function drainBuffer(string &$buffer, string $stream, callable $enqueue): void
    {
        while (($lineBreak = strpos($buffer, "\n")) !== false) {
            $line = trim(substr($buffer, 0, $lineBreak));
            $buffer = substr($buffer, $lineBreak + 1);

            if ($line === '') {
                continue;
            }

            $this->enqueueLine($line, $stream, $enqueue);
        }
    }

    /**
     * @param  callable(array<string, mixed>): void  $enqueue
     */
    private function flushBuffer(string &$buffer, string $stream, callable $enqueue): void
    {
        $line = trim($buffer);
        $buffer = '';

        if ($line !== '') {
            $this->enqueueLine($line, $stream, $enqueue);
        }
    }

    /**
     * @param  callable(array<string, mixed>): void  $enqueue
     */
    private function enqueueLine(string $line, string $stream, callable $enqueue): void
    {
        $decoded = json_decode($line, true);

        if (is_array($decoded) && isset($decoded['type'])) {
            $decoded['_stream'] = $stream;
            $enqueue($decoded);

            return;
        }

        $enqueue([
            'type' => 'cli.raw',
            'stream' => $stream,
            'message' => $line,
        ]);
    }
}
