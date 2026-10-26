<?php
namespace Andmarruda\Lpb\Contracts;

use Andmarruda\Lpb\ValueObjects\PublishState;

/**
 * Append-only history of entity snapshots.
 *
 * Revision arrays carry id, revisionable_type, revisionable_id, payload,
 * status, created_by and created_at. Revisions are never updated or deleted.
 */
interface RevisionRepositoryInterface
{
    /**
     * @param string $type Entity type, e.g. page or template
     * @param string|int $id Entity identifier
     * @param array $payload Full entity state at this moment
     * @param PublishState $state State the entity is in
     * @param string|null $createdBy Opaque user identifier
     * @return array The recorded revision
     */
    public function record(string $type, string|int $id, array $payload, PublishState $state, ?string $createdBy = null): array;

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
