<?php
namespace Andmarruda\Lpb\Exceptions;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Thrown when a write references a missing or incompatible related record,
 * such as a block whose parent belongs to another page. Renders as a 422
 * response.
 */
class InvalidRelationException extends InvalidArgumentException
{
    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 422);
    }
}
