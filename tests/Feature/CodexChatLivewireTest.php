<?php

namespace Tests\Feature;

use App\Livewire\CodexChat;
use App\Services\Codex\CodexCliStreamer;
use Generator;
use Livewire\Livewire;
use Tests\TestCase;

class CodexChatLivewireTest extends TestCase
{
    public function test_send_prompt_uses_livewire_action_to_stream_and_update_session_state(): void
    {
        config()->set('codex.workspace_root', base_path());

        $streamer = new class extends CodexCliStreamer
        {
            /**
             * @var array<string, mixed>
             */
            public array $captured = [];

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
                $this->captured = [
                    'prompt' => $prompt,
                    'session_id' => $sessionId,
                    'cwd' => $cwd,
                    'additional_directories' => $additionalDirectories,
                ];

                yield ['type' => 'thread.started', 'thread_id' => 'thread-livewire-1'];
                yield ['type' => 'cli.raw', 'stream' => 'stdout', 'message' => 'boot'];
                yield ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'Livewire response']];
                yield ['type' => 'process.exited', 'exit_code' => 0];
            }
        };

        $this->app->instance(CodexCliStreamer::class, $streamer);

        Livewire::test(CodexChat::class)
            ->set('prompt', 'Refactor this flow')
            ->call('sendPrompt')
            ->assertSet('sessionId', 'thread-livewire-1')
            ->assertSet('isStreaming', false)
            ->assertSee('Refactor this flow')
            ->assertSee('Livewire response')
            ->assertSee('[stdout] boot');

        $this->assertSame('Refactor this flow', $streamer->captured['prompt']);
        $this->assertSame(realpath(base_path()), $streamer->captured['cwd']);
    }

    public function test_directory_navigation_is_handled_by_livewire_state_actions(): void
    {
        config()->set('codex.workspace_root', base_path());

        $workspaceRoot = realpath(base_path()) ?: base_path();
        $appDirectory = realpath(base_path('app')) ?: base_path('app');

        Livewire::test(CodexChat::class)
            ->set('workspaceRootSelection', $appDirectory)
            ->assertSet('workspaceRoot', $appDirectory)
            ->assertSet('cwd', $appDirectory)
            ->set('workspaceRootSelection', $workspaceRoot)
            ->assertSet('workspaceRoot', $workspaceRoot)
            ->set('cwdSelection', $appDirectory)
            ->assertSet('cwd', $appDirectory)
            ->set('cwdSelection', '__up__')
            ->assertSet('cwd', $workspaceRoot);
    }
}
