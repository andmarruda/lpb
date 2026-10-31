<?php
namespace Andmarruda\Lpb\Exceptions;

use Illuminate\Http\JsonResponse;

/**
 * Thrown when a page tree, widget config or styles fail validation.
 *
 * Each error carries a JSON pointer into the submitted document
 * (e.g. /blocks/0/widgets/1/config/title) so editors can mark the field.
 */
class TreeValidationException extends InvalidValueException
{
    /**
     * @param array<int, array{path: string, message: string}> $errors
     */
    public function __construct(private array $errors)
    {
        $first = $errors[0] ?? ['path' => '', 'message' => 'Invalid page tree.'];
        $where = $first['path'] === '' ? '' : " at {$first['path']}";
        parent::__construct($first['message'] . $where . (count($errors) > 1 ? ' (and ' . (count($errors) - 1) . ' more errors)' : ''));
    }

    /**
     * @return array<int, array{path: string, message: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'errors' => $this->errors], 422);
    }
}
