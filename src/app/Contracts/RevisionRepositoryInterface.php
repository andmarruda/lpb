<?php
namespace Andmarruda\Lpb\Contracts;

/**
 * Published snapshots of entities (ADR 08).
 *
 * Revision arrays carry id (a ULID), revisionable_type, revisionable_id,
 * payload, status (always published), tree_version, created_by and
 * created_at. Revisions are never updated; they are deleted only by prune().
 */
interface RevisionRepositoryInterface
{
    /**
     * @param string $type Entity type, e.g. page
     * @param string|int $id Entity identifier
     * @param array $payload Full entity state at this moment
     * @param int $treeVersion The entity's tree_version the snapshot was taken at
     * @param string|null $createdBy Opaque user identifier
     * @return array The recorded revision
     */
    public function record(string $type, string|int $id, array $payload, int $treeVersion, ?string $createdBy = null): array;

    /**
     * A revision of an entity.
     *
     * @param string $type
     * @param string|int $id
     * @param string $revisionId
     * @return array|null Null if the revision does not exist or belongs to another entity
     */
    public function findFor(string $type, string|int $id, string $revisionId): ?array;

    /**
     * Delete the revisions of an entity beyond the newest $keep, never the protected one.
     *
     * @param string $type
     * @param string|int $id
     * @param int $keep
     * @param string|null $protectedId Revision that must be kept, e.g. the published one
     * @return int Number of revisions deleted
     */
    public function prune(string $type, string|int $id, int $keep, ?string $protectedId = null): int;

    /**
     * Revisions of an entity, newest first.
     *
     * @param string $type
     * @param string|int $id
     * @return array
     */
    public function getFor(string $type, string|int $id): array;

    public function getLatestFor(string $type, string|int $id): ?array;

    /**
     * The payload of a revision, for the caller to re-apply; nothing is changed.
     *
     * @param string $type
     * @param string|int $id
     * @param string|int $revisionId
     * @return array
     * @throws \Andmarruda\Lpb\Exceptions\InvalidRelationException If the revision does not belong to the entity
     */
    public function restore(string $type, string|int $id, string|int $revisionId): array;
}
