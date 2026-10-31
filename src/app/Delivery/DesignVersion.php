<?php
namespace Andmarruda\Lpb\Delivery;

use Andmarruda\Lpb\Contracts\SettingsRepositoryInterface;
use Andmarruda\Lpb\Events\DesignVersionBumped;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The site-wide design version (ADR 09): part of every content cache key,
 * bumped when something outside the snapshots changes how pages look
 * (templates, design tokens, a deployment that changes widgets).
 */
class DesignVersion
{
    public const SETTING = 'design_version';
    private const CACHE_KEY = 'lpb:design_version';

    public function __construct(
        private SettingsRepositoryInterface $settings,
        private DeliveryCache $cache,
        private Dispatcher $events,
    ) {
    }

    public function current(): int
    {
        return (int) $this->cache->rememberForever(self::CACHE_KEY, fn () => (int) $this->settings->get(self::SETTING, 1));
    }

    public function bump(): int
    {
        $version = $this->settings->increment(self::SETTING, 1);
        $this->cache->forget(self::CACHE_KEY);
        $this->events->dispatch(new DesignVersionBumped($version));
        return $version;
    }
}
