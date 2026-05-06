<?php

namespace Tests\Feature\SupportChat;

use App\Services\SupportChat\ClaudeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeClaudeClient;
use Tests\TestCase;

class ChatWidgetSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_file_exists_and_contains_expected_globals(): void
    {
        $path = public_path('vyt-chat.js');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        // Required behaviors / brand markers.
        $this->assertStringContainsString('/api/v1/support/chat', $contents);
        $this->assertStringContainsString('vyt_chat_session', $contents);
        $this->assertStringContainsString('Vaytoven Support', $contents);
        $this->assertStringContainsString('X-Vaytoven-Surface', $contents);
        // Brand gradient colors (FF3D8A → 7B2CBF) — keeps copy + design in sync.
        $this->assertStringContainsString('FF3D8A', $contents);
        $this->assertStringContainsString('7B2CBF', $contents);
    }

    public function test_widget_request_shape_is_accepted_by_chat_endpoint(): void
    {
        $fake = new FakeClaudeClient();
        $fake->enqueue([
            'id' => 'msg_smoke',
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => 'Hi there!']],
            'stop_reason' => 'end_turn',
        ]);
        $this->app->instance(ClaudeClient::class, $fake);

        // What the widget POSTs.
        $resp = $this->postJson('/api/v1/support/chat', [
            'message' => 'How does cancellation work?',
        ], [
            'X-Vaytoven-Surface' => 'web',
        ]);

        $resp->assertOk()
            ->assertJsonStructure(['session_id', 'reply'])
            ->assertJsonPath('reply', 'Hi there!');
    }

    public function test_widget_landing_page_serves_with_chat_script_tag(): void
    {
        $resp = $this->get('/');

        $resp->assertOk();
        $this->assertStringContainsString('/vyt-chat.js', $resp->getContent());
    }
}
