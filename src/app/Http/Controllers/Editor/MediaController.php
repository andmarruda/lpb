<?php
namespace Andmarruda\Lpb\Http\Controllers\Editor;

use Andmarruda\Lpb\Contracts\MediaRepositoryInterface;
use Andmarruda\Lpb\Http\Controllers\Controller;
use Andmarruda\Lpb\Media\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Editor API for media: uploads to the configured storage (a filesystem disk
 * such as local, S3 or R2, or the database), replacing files and records of
 * files stored elsewhere.
 */
class MediaController extends Controller
{
    public function __construct(private MediaRepositoryInterface $media, private MediaStorage $storage)
    {
    }

    /**
     * Upload a file: multipart "file", optional "collection", "alt" and "disk".
     */
    public function upload(Request $request): JsonResponse
    {
        $this->authorizeEdit();
        $data = $request->validate($this->fileRules() + [
            'collection' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:300'],
            'disk' => ['nullable', 'string', 'in:' . implode(',', $this->storage->disks())],
        ]);

        $media = $this->storage->upload($request->file('file'), ['collection' => $data['collection'] ?? null, 'meta' => array_filter(['alt' => $data['alt'] ?? null])], $data['disk'] ?? null);
        return new JsonResponse($media + ['url' => $this->storage->url($media)], 201);
    }

    /**
     * Replace the file of a media record; pages that use it show the new file.
     */
    public function replace(Request $request, string $media): JsonResponse
    {
        $this->authorizeEdit();
        if (!$this->media->find($media)) {
            return $this->notFound('Media not found.');
        }
        $request->validate($this->fileRules());

        $updated = $this->storage->replace($media, $request->file('file'));
        return new JsonResponse($updated + ['url' => $this->storage->url($updated)]);
    }

    private function fileRules(): array
    {
        return ['file' => [
            'required', 'file',
            'max:' . (int) config('laravel-page-builder.media.max_size', 10240),
            'mimetypes:' . implode(',', (array) config('laravel-page-builder.media.mimes', [])),
        ]];
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
        return $found ? new JsonResponse($found + ['url' => $this->storage->url($found)]) : $this->notFound('Media not found.');
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
        return $this->storage->delete($media) ? new JsonResponse(null, 204) : $this->notFound('Media not found.');
    }
}
