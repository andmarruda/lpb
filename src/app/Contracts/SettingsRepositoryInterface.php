<?php
namespace Andmarruda\Lpb\Contracts;

/**
 * Site-wide key/value settings (lpb_global_settings).
 */
interface SettingsRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    /**
     * Atomically increment an integer setting.
     *
     * @param string $key
     * @param int $start Value assumed when the setting does not exist yet
     * @return int The new value
     */
    public function increment(string $key, int $start = 0): int;
}
