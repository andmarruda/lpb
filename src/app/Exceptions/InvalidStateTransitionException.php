<?php
namespace Andmarruda\Lpb\Exceptions;

use DomainException;
use Andmarruda\Lpb\ValueObjects\PublishState;

/**
 * Thrown when a PublishState transition is not allowed.
 */
class InvalidStateTransitionException extends DomainException
{
    public static function between(PublishState $from, PublishState $to): self
    {
        return new self("Cannot transition from '{$from->value}' to '{$to->value}'.");
    }
}
