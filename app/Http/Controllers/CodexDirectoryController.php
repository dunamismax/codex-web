<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CodexDirectoryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['nullable', 'string', 'max:4096'],
        ]);

        $current = $this->resolveDirectory($validated['path'] ?? null);

        if ($current === null) {
            throw ValidationException::withMessages([
                'path' => 'Directory does not exist.',
            ]);
        }

        return response()->json([
            'current' => $current,
            'parent' => $this->parentDirectory($current),
            'children' => $this->childDirectories($current),
            'system_root' => $this->systemRoot($current),
            'home' => $this->homeDirectory(),
        ]);
    }

    private function resolveDirectory(?string $path): ?string
    {
        $target = filled($path)
            ? $path
            : (string) config('codex.workspace_root', base_path());

        if (! $this->isAbsolutePath($target)) {
            $target = base_path($target);
        }

        $resolved = realpath($target);

        if ($resolved === false || ! is_dir($resolved)) {
            return null;
        }

        return $resolved;
    }

    private function parentDirectory(string $path): ?string
    {
        $parent = dirname($path);

        return $parent === $path ? null : $parent;
    }

    /**
     * @return array<int, string>
     */
    private function childDirectories(string $path): array
    {
        $entries = @scandir($path);

        if ($entries === false) {
            return [];
        }

        $children = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $candidate = $path.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($candidate)) {
                $children[] = $candidate;
            }
        }

        sort($children);

        return array_slice($children, 0, 250);
    }

    private function systemRoot(string $path): string
    {
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1) {
            return strtoupper(substr($path, 0, 1)).':\\';
        }

        return DIRECTORY_SEPARATOR;
    }

    private function homeDirectory(): ?string
    {
        $home = getenv('HOME');

        if (is_string($home) && $home !== '') {
            $resolved = realpath($home);

            if ($resolved !== false && is_dir($resolved)) {
                return $resolved;
            }
        }

        return null;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '\\\\')
            || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1;
    }
}
