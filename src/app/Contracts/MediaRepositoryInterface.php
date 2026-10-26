<?php
namespace Andmarruda\Lpb\Contracts;

/**
 * Records of files already stored on a filesystem disk; no upload logic.
 *
 * create() requires disk, path, filename, mime_type and size (bytes);
 * meta (width, height, alt, title, focal_point, ...) and collection are
 * optional. A disk and path pair identifies one record.
 */
interface MediaRepositoryInterface extends RepositoryInterface
{
    public function findByPath(string $disk, string $path): ?array;

    /**
     * @param string $collection
     * @return array Media ordered by filename
     */
    public function getByCollection(string $collection): array;

    /**
     * @param string $mimeType An exact type (image/png) or a wildcard (image/*)
     * @return array Media ordered by filename
     */
    public function getByMimeType(string $mimeType): array;

    /**
     * Case-insensitive substring match on filename or path.
     *
     * @param string $term
     * @return array Media ordered by filename; empty for a blank term
     */
    public function search(string $term): array;
}
