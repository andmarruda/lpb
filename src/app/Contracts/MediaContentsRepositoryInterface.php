<?php
namespace Andmarruda\Lpb\Contracts;

/**
 * Contents of media stored in the database ("database" media disk).
 */
interface MediaContentsRepositoryInterface
{
    public function put(string|int $mediaId, string $contents): void;

    public function get(string|int $mediaId): ?string;

    public function delete(string|int $mediaId): void;
}
