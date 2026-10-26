<?php

namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Models\Sql\Media;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Repositories\Concerns\QueriesMedia;
use Andmarruda\Lpb\Contracts\MediaRepositoryInterface;

/**
 * SQL implementation of the Media repository.
 */
class MediaRepository extends BaseRepository implements MediaRepositoryInterface
{
    use QueriesMedia;

    /**
     * Create a new SQL media repository instance.
     *
     * @param Media $model The SQL Media model instance
     */
    public function __construct(Media $model)
    {
        parent::__construct($model);
    }

    /**
     * Create a media record.
     *
     * @param array $data The media data
     * @return array The created media as array
     */
    public function create(array $data): array
    {
        return parent::create($this->mediaAttributes($data, true));
    }

    /**
     * Update a media record.
     *
     * @param string|int $id The media identifier
     * @param array $data The data to update; unknown keys are ignored
     * @return array|null The updated media as array or null if not found
     */
    public function update(string|int $id, array $data): ?array
    {
        return parent::update($id, $this->mediaAttributes($data, false));
    }

    /**
     * Find a media record by disk and path.
     *
     * @param string $disk The filesystem disk
     * @param string $path The path on the disk
     * @return array|null The media as array or null if not found
     */
    public function findByPath(string $disk, string $path): ?array
    {
        return $this->model->where('disk', $disk)->where('path', $path)->first()?->toArray();
    }

    /**
     * Retrieve media of a collection.
     *
     * @param string $collection The collection name
     * @return array Media ordered by filename
     */
    public function getByCollection(string $collection): array
    {
        return $this->model->where('collection', $collection)->orderBy('filename')->orderBy('id')->get()->toArray();
    }

    /**
     * Retrieve media by mime type.
     *
     * @param string $mimeType An exact type (image/png) or a wildcard (image/*)
     * @return array Media ordered by filename
     */
    public function getByMimeType(string $mimeType): array
    {
        [$value, $wildcard] = $this->mimeFilter($mimeType);

        // Mime patterns never contain LIKE wildcards, so the prefix needs no escaping
        return $this->model
            ->where('mime_type', $wildcard ? 'like' : '=', $wildcard ? "{$value}%" : $value)
            ->orderBy('filename')->orderBy('id')
            ->get()->toArray();
    }

    /**
     * Case-insensitive substring search on filename or path.
     *
     * @param string $term The search term
     * @return array Media ordered by filename; empty for a blank term
     */
    public function search(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        // LOWER() and an explicit ESCAPE keep matching identical across SQLite, MySQL and PostgreSQL
        $pattern = '%' . strtr(mb_strtolower($term), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';

        return $this->model
            ->where(function ($query) use ($pattern) {
                $query->whereRaw("LOWER(filename) LIKE ? ESCAPE '!'", [$pattern])
                      ->orWhereRaw("LOWER(path) LIKE ? ESCAPE '!'", [$pattern]);
            })
            ->orderBy('filename')->orderBy('id')
            ->get()->toArray();
    }
}
