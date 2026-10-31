<?php
namespace Andmarruda\Lpb\Delivery;

use Andmarruda\Lpb\Events\PageArchived;
use Andmarruda\Lpb\Events\PageDeleted;
use Andmarruda\Lpb\Events\PagePublished;
use Andmarruda\Lpb\Events\PageRolledBack;
use Andmarruda\Lpb\Events\PageSlugChanged;
use Andmarruda\Lpb\Events\PageUnpublished;

/**
 * Keeps delivery pointers in step with publishing: the only invalidation
 * page content needs (ADR 09).
 */
class UpdatePointers
{
    public function __construct(private PageDeliveryService $delivery)
    {
    }

    public function served(PagePublished|PageRolledBack $event): void
    {
        $this->delivery->rememberPointer($event->slug, $event->pageId, (string) $event->revisionId);
    }

    public function withdrawn(PageUnpublished|PageArchived|PageDeleted $event): void
    {
        $this->delivery->forgetPointer($event->slug);
    }

    public function renamed(PageSlugChanged $event): void
    {
        $this->delivery->forgetPointer($event->oldSlug);
        $this->delivery->forgetPointer($event->newSlug);
    }

    /**
     * Events handled, mapped to the handling method.
     *
     * @return array<class-string, string>
     */
    public static function listens(): array
    {
        return [
            PagePublished::class => 'served',
            PageRolledBack::class => 'served',
            PageUnpublished::class => 'withdrawn',
            PageArchived::class => 'withdrawn',
            PageDeleted::class => 'withdrawn',
            PageSlugChanged::class => 'renamed',
        ];
    }
}
