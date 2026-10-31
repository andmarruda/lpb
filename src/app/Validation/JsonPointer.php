<?php
namespace Andmarruda\Lpb\Validation;

/**
 * JSON pointers (RFC 6901) into submitted documents, for error paths.
 */
final class JsonPointer
{
    public static function append(string $pointer, string|int ...$segments): string
    {
        foreach ($segments as $segment) {
            $pointer .= '/' . str_replace(['~', '/'], ['~0', '~1'], (string) $segment);
        }
        return $pointer;
    }
}
