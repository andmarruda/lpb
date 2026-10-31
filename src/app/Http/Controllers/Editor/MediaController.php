<?php
namespace Andmarruda\Lpb\Http\Controllers\Editor;

use Andmarruda\Lpb\Contracts\MediaRepositoryInterface;
use Andmarruda\Lpb\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Editor API for media records (files already stored on a disk; no uploads).
 */
class MediaController extends Controller
{
    public function __construct(private MediaRepositoryInterface $media)
    {
    }

    /**
     * All media, or filtered by ?collection=, ?mime_type= (image/png, image/*) or ?search=.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeEdit();

        $data = match (true) {
            $request->filled('collection') => $this->media->getByCollection((string) $request->query('collection')),
            $request->filled('mime_type') => $this->media->getByMimeType((string) $request->query('mime_type')),
            $request->filled('search') => $this->media->search((string) $request->query('search')),
            default => $this->media->all(),
        };
        return new JsonResponse(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeEdit();
        return new JsonResponse($this->media->create($request->all()), 201);
    }

    public function show(string $media): JsonResponse
    {
        $this->authorizeEdit();
        $found = $this->media->find($media);
        return $found ? new JsonResponse($found) : $this->notFound('Media not found.');
    }

    public function update(Request $request, string $media): JsonResponse
    {
        $this->authorizeEdit();
        $updated = $this->media->update($media, $request->all());
        return $updated ? new JsonResponse($updated) : $this->notFound('Media not found.');
    }

    public function destroy(string $media): JsonResponse
    {
        $this->authorizeEdit();
        return $this->media->delete($media) ? new JsonResponse(null, 204) : $this->notFound('Media not found.');
    }
}
