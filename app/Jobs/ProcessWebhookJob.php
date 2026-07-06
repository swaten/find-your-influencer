<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

// the actual webhook work happens here, never inline in the controller - the
// controller's job is to verify + dedupe + respond fast, not to do the work itself
class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $provider,
        public array $payload,
    ) {
    }

    public function handle(): void
    {
        // not wired to a live push subscription yet (our instagram integration calls
        // apify synchronously, it doesn't run async+webhook) - this proves the
        // verify/dedupe/queue path end to end, see README for what's left
        Log::channel('jobs')->info('webhook_processed', [
            'provider' => $this->provider,
            'payload' => $this->payload,
        ]);
    }
}
