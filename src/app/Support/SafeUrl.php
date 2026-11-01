<?php
namespace Andmarruda\Lpb\Support;

/**
 * URLs editors may store in links, menus and widget URL fields: http(s),
 * mailto and tel, relative paths, fragments and queries, or values without
 * any scheme (e.g. a bare e-mail address). javascript:, data: and other
 * schemes, protocol-relative "//host" and "/\host" are refused.
 */
final class SafeUrl
{
    /** Usable in PHP and as a JSON Schema (ECMA-262) pattern. */
    public const PATTERN = '^(?:(?:https?|mailto|tel):|[#?]|\.{1,2}/|/(?![/\\\\])|[^:/\\\\?#]+(?:[/\\\\?#]|$))';

    public static function isSafe(mixed $url): bool
    {
        return is_string($url) && preg_match('~' . self::PATTERN . '~i', preg_replace('/[\x00-\x20]+/', '', $url)) === 1;
    }
}
