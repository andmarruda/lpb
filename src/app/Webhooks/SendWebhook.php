<?php
namespace Andmarruda\Lpb\Webhooks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Queue\InteractsWithQueue;

/**
 * POSTs one signed webhook to one endpoint; a failed delivery is retried
 * with the configured backoff.
 */
class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries;

    /** @var int[] */
    public array $backoff;

    public int $timeout;

    public function __construct(public string $url, public string $body)
    {
        $this->tries = (int) config('laravel-page-builder.webhooks.tries', 5);
        $this->backoff = array_map('intval', (array) config('laravel-page-builder.webhooks.backoff', [10, 60, 300, 900]));
        $this->timeout = (int) config('laravel-page-builder.webhooks.timeout', 10);

        if ($queue = config('laravel-page-builder.webhooks.queue')) {
            $this->onQueue($queue);
        }
    }

    public function handle(Http $http): void
    {
        $http->timeout($this->timeout)
            ->withHeaders([WebhookSignature::HEADER => WebhookSignature::header($this->body, (string) config('laravel-page-builder.webhooks.secret'))])
            ->withBody($this->body, 'application/json')
            ->post($this->url)
            ->throw();
    }
}
