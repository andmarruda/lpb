<?php

namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Contracts\SettingsRepositoryInterface;
use Andmarruda\Lpb\Models\Sql\GlobalSetting;

/**
 * SQL implementation of the settings repository.
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

    public function increment(string $key, int $start = 0): int
    {
        return $this->model->getConnection()->transaction(function () use ($key, $start) {
            $setting = $this->model->newQuery()->where('key', $key)->lockForUpdate()->first();
            $value = ($setting ? (int) $setting->value : $start) + 1;
            $this->model->newQuery()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
            return $value;
        });
    }
}
