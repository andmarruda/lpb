<?php

namespace Andmarruda\Lpb\Repositories\Concerns;

use Andmarruda\Lpb\Exceptions\InvalidValueException;

/**
 * Validation of media input and query arguments shared by both drivers.
 */
trait QueriesMedia
{
    private const MIME_PATTERN = '#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#';

    /**
     * Extract and validate the writable media attributes.
     *
     * @param array $data
     * @param bool $creating Whether disk, path, filename, mime_type and size are required
     * @return array
     */
    protected function mediaAttributes(array $data, bool $creating): array
    {
        $attributes = array_intersect_key($data, array_flip(['disk', 'path', 'filename', 'mime_type', 'size', 'meta', 'collection']));

        foreach (['disk', 'path', 'filename', 'mime_type'] as $field) {
            if ($creating || array_key_exists($field, $attributes)) {
                $value = $attributes[$field] ?? null;
                if (!is_string($value) || trim($value) === '') {
                    throw new InvalidValueException("Media {$field} must be a non-empty string.");
                }
            }
        }

        if (array_key_exists('mime_type', $attributes)) {
            $attributes['mime_type'] = strtolower($attributes['mime_type']);
            if (!preg_match(self::MIME_PATTERN, $attributes['mime_type'])) {
                throw new InvalidValueException("Invalid media mime_type '{$attributes['mime_type']}'.");
            }
        }

        if ($creating || array_key_exists('size', $attributes)) {
            $size = $attributes['size'] ?? null;
            if (!is_int($size) || $size < 0) {
                throw new InvalidValueException('Media size must be a non-negative integer of bytes.');
            }
        }

        if (array_key_exists('meta', $attributes) && $attributes['meta'] !== null && !is_array($attributes['meta'])) {
            throw new InvalidValueException('Media meta must be an array or null.');
        }

        if (array_key_exists('collection', $attributes) && $attributes['collection'] !== null && !is_string($attributes['collection'])) {
            throw new InvalidValueException('Media collection must be a string or null.');
        }

        return $attributes;
    }

    /**
     * Split a mime type filter into [exact type or prefix, isWildcard].
     *
     * Accepts an exact type (image/png) or a whole-type wildcard (image/*).
     *
     * @param string $mimeType
     * @return array{0: string, 1: bool}
     */
    protected function mimeFilter(string $mimeType): array
    {
        $mimeType = strtolower(trim($mimeType));
        if (str_ends_with($mimeType, '/*')) {
            $type = substr($mimeType, 0, -2);
            if (preg_match(self::MIME_PATTERN, "{$type}/x")) {
                return ["{$type}/", true];
            }
        } elseif (preg_match(self::MIME_PATTERN, $mimeType)) {
            return [$mimeType, false];
        }

        throw new InvalidValueException("Invalid mime type filter '{$mimeType}'.");
    }
}
