<?php
namespace Andmarruda\Lpb\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A media file was replaced or deleted: delivered payloads hold its old URL.
 */
final class MediaChanged
{
    use Dispatchable;

    public function __construct(public readonly string $mediaId)
    {
    }
}
