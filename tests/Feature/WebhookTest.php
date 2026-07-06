<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, config('services.webhooks.apify'));
    }

    // ── positive: valid signature is accepted and queues the real work ────

    public function test_a_correctly_signed_webhook_is_accepted_and_queues_a_job(): void
    {
        Queue::fake();

        $body = json_encode(['eventId' => 'evt-1', 'status' => 'SUCCEEDED']);

        $response = $this->call('POST', '/api/webhooks/apify', [], [], [], [
            'HTTP_X-Webhook-Signature' => $this->sign($body),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(202);
        Queue::assertPushed(ProcessWebhookJob::class, fn (ProcessWebhookJob $job) => $job->provider === 'apify');
    }

    // ── negative: wrong/missing signature is rejected, no job queued ──────

    public function test_a_webhook_with_an_invalid_signature_is_rejected(): void
    {
        Queue::fake();

        $body = json_encode(['eventId' => 'evt-2', 'status' => 'SUCCEEDED']);

        $response = $this->call('POST', '/api/webhooks/apify', [], [], [], [
            'HTTP_X-Webhook-Signature' => 'not-the-real-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(401);
        Queue::assertNotPushed(ProcessWebhookJob::class);
    }

    // ── negative (idempotency): a replayed event is not reprocessed ────────

    public function test_a_replayed_event_is_acknowledged_but_not_reprocessed(): void
    {
        Queue::fake();

        $body = json_encode(['eventId' => 'evt-3', 'status' => 'SUCCEEDED']);
        $headers = [
            'HTTP_X-Webhook-Signature' => $this->sign($body),
            'CONTENT_TYPE' => 'application/json',
        ];

        $first = $this->call('POST', '/api/webhooks/apify', [], [], [], $headers, $body);
        $first->assertStatus(202);

        $second = $this->call('POST', '/api/webhooks/apify', [], [], [], $headers, $body);
        $second->assertStatus(200)->assertJson(['message' => 'Already processed.']);

        Queue::assertPushed(ProcessWebhookJob::class, 1);
    }

    public function test_an_unknown_provider_returns_404(): void
    {
        $body = json_encode(['eventId' => 'evt-4']);

        $response = $this->call('POST', '/api/webhooks/not-a-real-provider', [], [], [], [
            'HTTP_X-Webhook-Signature' => 'irrelevant',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(404);
    }
}
