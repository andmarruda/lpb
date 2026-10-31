<?php
namespace Andmarruda\Lpb\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A change to what visitors are served for a page (ADR 08). Listeners keep
 * the delivery pointer up to date and send webhooks.
 */
abstract class PageEvent
{
    use Dispatchable;

    /**
     * @param string $pageId
     * @param string $slug
     * @param string|null $revisionId The revision now served, or null when the page is not served
     * @param string|null $by Opaque user identifier
     */
    public function __construct(
        public readonly string $pageId,
        public readonly string $slug,
        public readonly ?string $revisionId,
        public readonly ?string $by = null,
    ) {
    }
}
