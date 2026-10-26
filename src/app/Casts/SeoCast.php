<?php
namespace Andmarruda\Lpb\Casts;

use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\ValueObjects\SEO;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts the seo attribute to the SEO value object.
 *
 * Accepts an SEO instance, an array with snake_case keys or null on write;
 * an empty SEO is stored as null. Stored as a JSON string by default, or as
 * an embedded document with SeoCast::class . ':document' (MongoDB).
 * Serialised with toArray() as an array.
 */
class SeoCast implements CastsAttributes, SerializesCastableAttributes
{
    public function __construct(private string $storage = 'json')
    {
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?SEO
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? SEO::fromJsonString($value) : SEO::fromArray((array) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $seo = match (true) {
            $value === null => null,
            $value instanceof SEO => $value,
            is_array($value) => SEO::fromArray($value),
            default => throw new InvalidValueException('SEO must be an SEO instance, an array or null.'),
        };

        if ($seo === null || $seo->isEmpty()) {
            return [$key => null];
        }

        return [$key => $this->storage === 'document' ? $seo->toArray() : $seo->toJsonString()];
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return $value?->toArray();
    }
}
