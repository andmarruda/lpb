<?php
namespace Andmarruda\Lpb\Exceptions;

use Illuminate\Http\JsonResponse;

/**
 * Thrown when an operation targets a page or revision that does not exist.
 */
class RecordNotFoundException extends InvalidRelationException
{
    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 404);
    }
}
