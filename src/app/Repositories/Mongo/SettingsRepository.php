<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

use Andmarruda\Lpb\Contracts\SettingsRepositoryInterface;
use Andmarruda\Lpb\Models\Mongo\GlobalSetting;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * MongoDB implementation of the settings repository.
 */
class SettingsRepository implements SettingsRepositoryInterface
{
    public function __construct(protected GlobalSetting $model)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->model->newQuery()->where('key', $key)->value('value') ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->model->newQuery()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * $inc on the stored integer; the first increment creates the setting.
     */
    public function increment(string $key, int $start = 0): int
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($this->model->newQuery()->where('key', $key)->increment('value') > 0) {
                return (int) $this->get($key);
            }

            try {
                $this->model->newQuery()->create(['key' => $key, 'value' => $start + 1]);
                return $start + 1;
            } catch (BulkWriteException) {
                // Created concurrently (unique key index): increment it instead
            }
        }

        return (int) $this->get($key);
    }
}
