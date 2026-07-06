<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WebhookController extends Controller
{
    // must verify + dedupe + respond in well under 2s - the real work always goes
    // through a queued job, never runs inline here
    public function handle(Request $request, string $provider)
    {
        $secret = config("services.webhooks.{$provider}");

        if (! $secret) {
            abort(404);
        }

        $signature = (string) $request->header('X-Webhook-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            Log::channel('jobs')->warning('webhook_signature_mismatch', ['provider' => $provider]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        // prefer the provider's own event id for dedup; fall back to a payload hash
        // if it doesn't send one, so replays of an identical body are still caught
        $nonce = $request->input('eventId') ?? hash('sha256', $request->getContent());
        $nonceKey = "webhook:{$provider}:nonce:{$nonce}";

        // NX = only the first request in the 24h window claims this key
        $isNew = Redis::set($nonceKey, '1', 'EX', 86400, 'NX');

        if (! $isNew) {
            return response()->json(['message' => 'Already processed.']);
        }

        ProcessWebhookJob::dispatch($provider, $request->all());

        return response()->json(['message' => 'Accepted.'], 202);
    }
}
