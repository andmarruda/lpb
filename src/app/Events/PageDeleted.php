<?php
namespace Andmarruda\Lpb\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A page was deleted; it is no longer served.
 */
final class PageDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $pageId,
        public readonly string $slug,
    ) {
    }
}
