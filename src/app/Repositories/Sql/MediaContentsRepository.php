<?php

namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Contracts\MediaContentsRepositoryInterface;
use Andmarruda\Lpb\Models\Sql\MediaFile;

/**
 * Stores media contents base64-encoded in lpb_media_files.
 */
class MediaContentsRepository implements MediaContentsRepositoryInterface
{
    public function __construct(protected MediaFile $model)
    {
    }

    public function put(string|int $mediaId, string $path, string $contents): void
    {
        $this->model->newQuery()->updateOrCreate(['media_id' => (string) $mediaId], ['path' => $path, 'contents' => base64_encode($contents)]);
    }

    public function get(string|int $mediaId, string $path): ?string
    {
        $contents = $this->model->newQuery()->where('media_id', (string) $mediaId)->where('path', $path)->value('contents');
        return $contents === null ? null : (base64_decode($contents, true) ?: null);
    }

    public function delete(string|int $mediaId): void
    {
        $this->model->newQuery()->where('media_id', (string) $mediaId)->delete();
    }
}
