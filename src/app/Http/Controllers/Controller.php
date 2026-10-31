<?php
namespace Andmarruda\Lpb\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Base controller of the package's JSON APIs.
 */
abstract class Controller
{
    protected function authorizeEdit(): void
    {
        Gate::authorize('lpb.edit');
    }

    protected function authorizePublish(): void
    {
        Gate::authorize('lpb.publish');
    }

    /**
     * Opaque identifier of the acting user, stored on revisions and events.
     */
    protected function actor(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();
        return $id === null ? null : (string) $id;
    }

    protected function notFound(string $message = 'Not found.'): JsonResponse
    {
        return new JsonResponse(['message' => $message], 404);
    }
}
