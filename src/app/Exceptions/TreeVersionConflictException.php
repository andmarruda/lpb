<?php
namespace Andmarruda\Lpb\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when a tree is saved against a tree_version that is no longer the
 * page's current one: someone else saved in the meantime.
 */
class TreeVersionConflictException extends RuntimeException
{
    public function __construct(private int $currentVersion, int $expectedVersion)
    {
        parent::__construct("The page tree changed (version {$currentVersion}, expected {$expectedVersion}); reload it and apply your changes again.");
    }

    public function currentVersion(): int
    {
        return $this->currentVersion;
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'current_version' => $this->currentVersion], 409);
    }
}
