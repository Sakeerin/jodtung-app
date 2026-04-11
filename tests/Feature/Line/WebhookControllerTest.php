<?php

namespace Tests\Feature\Line;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_missing_signature(): void
    {
        $response = $this->postJson('/api/line/webhook', [
            'events' => [],
        ]);

        // Without signature header, the middleware should reject
        $response->assertStatus(401);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $response = $this->withHeaders([
            'X-Line-Signature' => 'invalid_signature_value',
        ])->postJson('/api/line/webhook', [
            'events' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_health_check_endpoint(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure(['status', 'timestamp', 'app'])
            ->assertJsonPath('status', 'ok');
    }
}
