<?php
namespace Andmarruda\Lpb\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A page's slug changed; the delivery pointer of the old slug is stale.
 */
final class PageSlugChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $pageId,
        public readonly string $oldSlug,
        public readonly string $newSlug,
    ) {
    }
}
