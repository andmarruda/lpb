<?php
namespace Andmarruda\Lpb\ValueObjects;

use Andmarruda\Lpb\Exceptions\InvalidStateTransitionException;

/**
 * Publication state of a page or template.
 *
 * Allowed transitions:
 *   draft     -> published | archived
 *   published -> draft | archived
 *   archived  -> draft
 */
enum PublishState: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * Whether this state can move to published.
     *
     * @return bool
     */
    public function isPublishable(): bool
    {
        return $this->canTransitionTo(self::Published);
    }

    /**
     * Whether content in this state is visible to visitors.
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this === self::Published;
    }

    /**
     * @param self $next
     * @return bool
     */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [self::Published, self::Archived],
            self::Published => [self::Draft, self::Archived],
            self::Archived => [self::Draft],
        }, true);
    }

    /**
     * Move to the next state, rejecting illegal transitions.
     *
     * @param self $next
     * @return self
     * @throws InvalidStateTransitionException
     */
    public function transitionTo(self $next): self
    {
        if (!$this->canTransitionTo($next)) {
            throw InvalidStateTransitionException::between($this, $next);
        }

        return $next;
    }
}
