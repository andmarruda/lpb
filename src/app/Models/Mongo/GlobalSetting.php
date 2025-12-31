<?php
namespace Andmarruda\Lpb\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class GlobalSetting extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'lpb_global_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a global setting by key
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting?->value ?? $default;
    }

    /**
     * Set a global setting by key
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
