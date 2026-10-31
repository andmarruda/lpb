<?php
namespace Andmarruda\Lpb\Exceptions;

use DomainException;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Illuminate\Http\JsonResponse;

/**
 * Thrown when a PublishState transition is not allowed. Renders as a 409
 * response.
 */
class InvalidStateTransitionException extends DomainException
{
    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 409);
    }

    public static function between(PublishState $from, PublishState $to): self
    {
        return new self("Cannot transition from '{$from->value}' to '{$to->value}'.");
    }
}
