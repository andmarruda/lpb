<?php
namespace Andmarruda\Lpb\Delivery;

use Closure;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;

/**
 * The cache store used for delivery (laravel-page-builder.cache): content
 * entries, pointers and the design version. When caching is disabled,
 * every value is computed on each call.
 */
class DeliveryCache
{
    public function __construct(private Factory $cache)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('laravel-page-builder.cache.enabled', true);
    }

    public function get(string $key): mixed
    {
        return $this->enabled() ? $this->store()->get($key) : null;
    }

    public function put(string $key, mixed $value, ?int $ttl): void
    {
        if ($this->enabled()) {
            $ttl === null ? $this->store()->forever($key, $value) : $this->store()->put($key, $value, $ttl);
        }
    }

    public function remember(string $key, ?int $ttl, Closure $value): mixed
    {
        if (!$this->enabled()) {
            return $value();
        }
        return $ttl === null ? $this->store()->rememberForever($key, $value) : $this->store()->remember($key, $ttl, $value);
    }

    public function rememberForever(string $key, Closure $value): mixed
    {
        return $this->remember($key, null, $value);
    }

    public function forget(string $key): void
    {
        if ($this->enabled()) {
            $this->store()->forget($key);
        }
    }

    /**
     * Pointer lifetime: short, they are rewritten on every publishing event.
     */
    public function pointerTtl(): ?int
    {
        $ttl = config('laravel-page-builder.cache.ttl', 3600);
        return $ttl === null ? null : (int) $ttl;
    }

    /**
     * Content lifetime: content entries never change, so the default is forever.
     */
    public function contentTtl(): ?int
    {
        $ttl = config('laravel-page-builder.cache.content_ttl');
        return $ttl === null ? null : (int) $ttl;
    }

    private function store(): Repository
    {
        return $this->cache->store(config('laravel-page-builder.cache.store'));
    }
}
