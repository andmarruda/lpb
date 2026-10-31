<?php
namespace Andmarruda\Lpb\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The site-wide design version changed: every content cache key is new (ADR 09).
 */
final class DesignVersionBumped
{
    use Dispatchable;

    public function __construct(public readonly int $designVersion)
    {
    }
}
