<?php
namespace Andmarruda\Lpb\Support;

use Illuminate\Support\Str;

/**
 * ULIDs used as ids of blocks, widget instances and revisions on both drivers.
 *
 * Ids are stored uppercase (the canonical Crockford form) so an id sent by
 * a client in lowercase still matches the stored one.
 */
final class Ulid
{
    private const PATTERN = '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i';

    public static function generate(): string
    {
        return strtoupper((string) Str::ulid());
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    public static function normalize(string $value): string
    {
        return strtoupper($value);
    }
}
