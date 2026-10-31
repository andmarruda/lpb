<?php
namespace Andmarruda\Lpb\Webhooks;

use Andmarruda\Lpb\Delivery\DesignVersion;
use Andmarruda\Lpb\Events\DesignVersionBumped;
use Andmarruda\Lpb\Events\PageArchived;
use Andmarruda\Lpb\Events\PageEvent;
use Andmarruda\Lpb\Events\PagePublished;
use Andmarruda\Lpb\Events\PageRolledBack;
use Andmarruda\Lpb\Events\PageUnpublished;
use Andmarruda\Lpb\ValueObjects\CacheKey;
use Illuminate\Contracts\Bus\Dispatcher as Bus;

/**
 * Queues a signed webhook per configured endpoint on publish, unpublish,
 * archive, rollback and design bumps, so external frontends can revalidate
 * their own caches. Nothing is sent without endpoints and a secret.
 *
 * Body: {event, site, locale, slug, revision_id, design_version}.
 */
class NotifyWebhooks
{
    private const EVENTS = [
        PagePublished::class => 'page.published',
        PageUnpublished::class => 'page.unpublished',
        PageArchived::class => 'page.archived',
        PageRolledBack::class => 'page.rolled_back',
    ];

    public function __construct(private Bus $bus, private DesignVersion $design)
    {
    }

    public function page(PageEvent $event): void
    {
        $this->send([
            'event' => self::EVENTS[$event::class],
            'site' => CacheKey::DEFAULT,
            'locale' => CacheKey::DEFAULT,
            'slug' => $event->slug,
            'revision_id' => $event->revisionId,
            'design_version' => $this->design->current(),
        ]);
    }

    public function design(DesignVersionBumped $event): void
    {
        $this->send([
            'event' => 'design.bumped',
            'site' => CacheKey::DEFAULT,
            'locale' => CacheKey::DEFAULT,
            'slug' => null,
            'revision_id' => null,
            'design_version' => $event->designVersion,
        ]);
    }

    /**
     * Events handled, mapped to the handling method.
     *
     * @return array<class-string, string>
     */
    public static function listens(): array
    {
        return array_fill_keys(array_keys(self::EVENTS), 'page') + [DesignVersionBumped::class => 'design'];
    }

    private function send(array $payload): void
    {
        $endpoints = array_filter((array) config('laravel-page-builder.webhooks.endpoints', []));
        if ($endpoints === [] || !config('laravel-page-builder.webhooks.secret')) {
            return;
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        foreach ($endpoints as $url) {
            $this->bus->dispatch(new SendWebhook($url, $body));
        }
    }
}
