<?php

namespace Tests\Feature;

use App\Services\Codex\CodexCliStreamer;
use Generator;
use RuntimeException;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_directories_endpoint_returns_current_parent_and_children(): void
    {
        $workspace = sys_get_temp_dir().DIRECTORY_SEPARATOR.'codex-web-dir-test';
        $child = $workspace.DIRECTORY_SEPARATOR.'child';
        @mkdir($child, 0777, true);

        try {
            $response = $this->getJson(route('codex.directories', ['path' => $workspace]));

            $response->assertOk();
            $response->assertJsonPath('current', realpath($workspace));
            $response->assertJsonPath('children.0', realpath($child));
            $response->assertJsonPath('parent', dirname(realpath($workspace)));
        } finally {
            @rmdir($child);
            @rmdir($workspace);
        }
    }

    public function test_chat_page_renders(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Codex Stream Console');
    }

    public function test_stream_endpoint_rejects_cwd_outside_workspace_root(): void
    {
        config()->set('codex.workspace_root', base_path());

        $response = $this->postJson(route('codex.stream'), [
            'prompt' => 'Hello',
            'cwd' => '/tmp',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cwd']);
    }

    public function test_stream_endpoint_rejects_unsupported_model(): void
    {
        config()->set('codex.workspace_root', base_path());

        $response = $this->postJson(route('codex.stream'), [
            'prompt' => 'Hello',
            'cwd' => base_path(),
            'model' => 'o3',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['model']);
    }

    public function test_stream_endpoint_accepts_custom_workspace_root_and_directory(): void
    {
        $workspace = sys_get_temp_dir().DIRECTORY_SEPARATOR.'codex-web-workspace-test';
        @mkdir($workspace, 0777, true);

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
                    'cwd' => $cwd,
                    'additional_directories' => $additionalDirectories,
                ];

                yield ['type' => 'process.exited', 'exit_code' => 0];
            }
        };

        $this->app->instance(CodexCliStreamer::class, $streamer);

        try {
            $response = $this->post(route('codex.stream'), [
                'prompt' => 'hello',
                'workspace_root' => $workspace,
                'cwd' => $workspace,
                'add_dirs' => [$workspace],
            ], [
                'Accept' => 'text/event-stream',
            ]);

            $response->assertOk();
            $response->streamedContent();

            $this->assertSame(realpath($workspace), $streamer->captured['cwd']);
            $this->assertSame([realpath($workspace)], $streamer->captured['additional_directories']);
        } finally {
            @rmdir($workspace);
        }
    }

    public function test_stream_endpoint_returns_sse_output(): void
    {
        config()->set('codex.workspace_root', base_path());

        $this->app->instance(CodexCliStreamer::class, new class extends CodexCliStreamer
        {
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
                yield ['type' => 'thread.started', 'thread_id' => 'thread-123'];
                yield ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'hello']];
                yield ['type' => 'process.exited', 'exit_code' => 0];
            }
        });

        $response = $this->post(route('codex.stream'), [
            'prompt' => 'hello',
            'cwd' => base_path(),
        ], [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();

        $streamed = $response->streamedContent();

        $this->assertStringContainsString('event: codex', $streamed);
        $this->assertStringContainsString('"type":"thread.started"', $streamed);
        $this->assertStringContainsString('event: done', $streamed);
    }

    public function test_stream_endpoint_forwards_runtime_options_to_streamer(): void
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
                    'model' => $model,
                    'reasoning_effort' => $reasoningEffort,
                    'full_auto' => $fullAuto,
                    'cwd' => $cwd,
                    'sandbox_mode' => $sandboxMode,
                    'approval_policy' => $approvalPolicy,
                    'web_search' => $webSearch,
                    'additional_directories' => $additionalDirectories,
                ];

                yield ['type' => 'process.exited', 'exit_code' => 0];
            }
        };

        $this->app->instance(CodexCliStreamer::class, $streamer);

        $response = $this->post(route('codex.stream'), [
            'prompt' => 'hello',
            'session_id' => 'thread-123',
            'cwd' => base_path(),
            'model' => 'gpt-5.2-codex',
            'reasoning_effort' => 'low',
            'full_auto' => false,
            'sandbox_mode' => 'read-only',
            'approval_policy' => 'untrusted',
            'web_search' => true,
            'add_dirs' => [base_path('app')],
        ], [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();
        $response->streamedContent();

        $this->assertSame('gpt-5.2-codex', $streamer->captured['model']);
        $this->assertSame('low', $streamer->captured['reasoning_effort']);
        $this->assertFalse($streamer->captured['full_auto']);
        $this->assertSame('read-only', $streamer->captured['sandbox_mode']);
        $this->assertSame('untrusted', $streamer->captured['approval_policy']);
        $this->assertTrue($streamer->captured['web_search']);
        $this->assertSame([realpath(base_path('app'))], $streamer->captured['additional_directories']);
    }

    public function test_stream_endpoint_uses_configured_model_and_effort_defaults_when_not_provided(): void
    {
        config()->set('codex.workspace_root', base_path());
        config()->set('codex.default_model', 'gpt-5.3-codex');
        config()->set('codex.default_reasoning_effort', 'high');

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
                    'model' => $model,
                    'reasoning_effort' => $reasoningEffort,
                ];

                yield ['type' => 'process.exited', 'exit_code' => 0];
            }
        };

        $this->app->instance(CodexCliStreamer::class, $streamer);

        $response = $this->post(route('codex.stream'), [
            'prompt' => 'hello',
            'cwd' => base_path(),
        ], [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();
        $response->streamedContent();

        $this->assertSame('gpt-5.3-codex', $streamer->captured['model']);
        $this->assertSame('high', $streamer->captured['reasoning_effort']);
    }

    public function test_stream_endpoint_emits_error_event_when_streamer_fails(): void
    {
        config()->set('codex.workspace_root', base_path());

        $this->app->instance(CodexCliStreamer::class, new class extends CodexCliStreamer
        {
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
                throw new RuntimeException('Codex binary was not found.');
            }
        });

        $response = $this->post(route('codex.stream'), [
            'prompt' => 'hello',
            'cwd' => base_path(),
        ], [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();

        $streamed = $response->streamedContent();

        $this->assertStringContainsString('event: codex', $streamed);
        $this->assertStringContainsString('"type":"error"', $streamed);
        $this->assertStringContainsString('Codex binary was not found.', $streamed);
        $this->assertStringContainsString('event: done', $streamed);
    }

    public function test_streamer_build_command_forwards_approval_and_web_search_when_not_full_auto(): void
    {
        config()->set('codex.binary', 'codex');
        config()->set('codex.skip_git_repo_check', true);

        $streamer = new CodexCliStreamer;
        $buildCommand = new \ReflectionMethod(CodexCliStreamer::class, 'buildCommand');
        $buildCommand->setAccessible(true);

        /** @var array<int, string> $command */
        $command = $buildCommand->invoke(
            $streamer,
            null,
            'gpt-5.2-codex',
            'high',
            false,
            base_path(),
            'workspace-write',
            'on-request',
            true,
            []
        );

        $this->assertSame('codex', $command[0]);
        $this->assertContains('--ask-for-approval', $command);
        $this->assertContains('on-request', $command);
        $this->assertContains('--search', $command);
        $this->assertContains('exec', $command);
    }

    public function test_streamer_build_command_skips_approval_when_full_auto_enabled(): void
    {
        config()->set('codex.binary', 'codex');
        config()->set('codex.skip_git_repo_check', true);

        $streamer = new CodexCliStreamer;
        $buildCommand = new \ReflectionMethod(CodexCliStreamer::class, 'buildCommand');
        $buildCommand->setAccessible(true);

        /** @var array<int, string> $command */
        $command = $buildCommand->invoke(
            $streamer,
            null,
            'gpt-5.2-codex',
            'high',
            true,
            base_path(),
            'workspace-write',
            'never',
            true,
            []
        );

        $this->assertContains('--full-auto', $command);
        $this->assertNotContains('--ask-for-approval', $command);
        $this->assertContains('--search', $command);
    }
}
