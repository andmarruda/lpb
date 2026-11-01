<?php
namespace Andmarruda\Lpb\Contracts;

/**
 * Contents of media stored in the database ("database" media disk).
 */
interface MediaContentsRepositoryInterface
{
    /**
     * Store the contents of a media record under the record's path.
     */
    public function put(string|int $mediaId, string $path, string $contents): void;

    /**
     * The contents, or null when none are stored for that path.
     */
    public function get(string|int $mediaId, string $path): ?string;

    public function delete(string|int $mediaId): void;
}
