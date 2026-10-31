<?php
namespace Andmarruda\Lpb\Webhooks;

/**
 * X-Lpb-Signature: t={unix time},v1={hex HMAC-SHA256 of "{t}.{body}"}.
 *
 * Receivers recompute the HMAC with the shared secret over the raw request
 * body and reject old timestamps to stop replays.
 */
final class WebhookSignature
{
    public const HEADER = 'X-Lpb-Signature';

    public static function header(string $body, string $secret, ?int $time = null): string
    {
        $time ??= time();
        return "t={$time},v1=" . hash_hmac('sha256', "{$time}.{$body}", $secret);
    }

    public static function verify(string $body, string $header, string $secret, int $tolerance = 300, ?int $now = null): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        if (!isset($parts['t'], $parts['v1']) || !ctype_digit($parts['t'])) {
            return false;
        }
        if (abs(($now ?? time()) - (int) $parts['t']) > $tolerance) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', "{$parts['t']}.{$body}", $secret), $parts['v1']);
    }
}
