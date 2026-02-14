<?php

namespace Tests\Feature;

use App\Services\Codex\CodexCliStreamer;
use Generator;
use RuntimeException;
use Tests\TestCase;

class ExampleTest extends TestCase
{
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

    public function test_stream_endpoint_returns_sse_output(): void
    {
        config()->set('codex.workspace_root', base_path());

        $this->app->instance(CodexCliStreamer::class, new class extends CodexCliStreamer
        {
            public function stream(
                string $prompt,
                ?string $sessionId = null,
                ?string $model = null,
                bool $fullAuto = false,
                ?string $cwd = null
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

    public function test_stream_endpoint_emits_error_event_when_streamer_fails(): void
    {
        config()->set('codex.workspace_root', base_path());

        $this->app->instance(CodexCliStreamer::class, new class extends CodexCliStreamer
        {
            public function stream(
                string $prompt,
                ?string $sessionId = null,
                ?string $model = null,
                bool $fullAuto = false,
                ?string $cwd = null
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
}
