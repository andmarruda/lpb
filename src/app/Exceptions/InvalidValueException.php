<?php
namespace Andmarruda\Lpb\Exceptions;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Thrown when a value object is constructed from an invalid value, or when
 * input to a repository or service is invalid. Renders as a 422 response.
 */
class InvalidValueException extends InvalidArgumentException
{
    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 422);
    }
}
