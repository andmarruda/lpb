<?php

namespace Andmarruda\Lpb\Repositories\Concerns;

use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Illuminate\Database\Eloquent\Builder;

/**
 * Revision storage shared by both drivers; expects a $model property.
 *
 * Revision ids are ULIDs on SQL and ObjectId strings on MongoDB.
 */
trait ManagesRevisions
{
    /**
     * Record a published snapshot of an entity.
     *
     * @param string $type Entity type, e.g. page
     * @param string|int $id Entity identifier
     * @param array $payload Full entity state at this moment
     * @param int $treeVersion The entity's tree_version the snapshot was taken at
     * @param string|null $createdBy Opaque user identifier
     * @return array The recorded revision as array
     */
    public function record(string $type, string|int $id, array $payload, int $treeVersion, ?string $createdBy = null): array
    {
        if (trim($type) === '') {
            throw new InvalidValueException('Revision type must be a non-empty string.');
        }

        return $this->model->create([
            'revisionable_type' => $type,
            'revisionable_id' => (string) $id,
            'payload' => $payload,
            'status' => PublishState::Published,
            'tree_version' => $treeVersion,
            'created_by' => $createdBy,
        ])->toArray();
    }

    /**
     * A revision of an entity.
     *
     * @param string $type
     * @param string|int $id
     * @param string $revisionId
     * @return array|null Null if the revision does not exist or belongs to another entity
     */
    public function findFor(string $type, string|int $id, string $revisionId): ?array
    {
        return $this->revisionsOf($type, $id)->find($revisionId)?->toArray();
    }

    /**
     * Delete the revisions of an entity beyond the newest $keep, never the protected one.
     *
     * Deletes through the query builder: the model refuses deletes, so
     * pruning is the only way revisions are removed.
     *
     * @param string $type
     * @param string|int $id
     * @param int $keep
     * @param string|null $protectedId
     * @return int Number of revisions deleted
     */
    public function prune(string $type, string|int $id, int $keep, ?string $protectedId = null): int
    {
        $ids = $this->revisionsOf($type, $id)->pluck($this->model->getKeyName())->map(fn ($key) => (string) $key)->all();
        $stale = array_values(array_diff(array_slice($ids, max(0, $keep)), array_filter([$protectedId])));
        if ($stale === []) {
            return 0;
        }

        $this->model->newQuery()->whereIn($this->model->getKeyName(), $stale)->delete();
        return count($stale);
    }

    /**
     * Retrieve the revisions of an entity, newest first.
     *
     * @param string $type Entity type
     * @param string|int $id Entity identifier
     * @return array Revisions as array
     */
    public function getFor(string $type, string|int $id): array
    {
        return $this->revisionsOf($type, $id)->get()->toArray();
    }

    /**
     * Retrieve the newest revision of an entity.
     *
     * @param string $type Entity type
     * @param string|int $id Entity identifier
     * @return array|null The revision as array or null if there is none
     */
    public function getLatestFor(string $type, string|int $id): ?array
    {
        return $this->revisionsOf($type, $id)->first()?->toArray();
    }

    /**
     * Return the payload of a revision without changing anything.
     *
     * @param string $type Entity type
     * @param string|int $id Entity identifier
     * @param string|int $revisionId Revision identifier
     * @return array The revision payload
     */
    public function restore(string $type, string|int $id, string|int $revisionId): array
    {
        $revision = $this->revisionsOf($type, $id)->find($revisionId)
            ?? throw new InvalidRelationException("Revision '{$revisionId}' not found for {$type} '{$id}'.");

        return $revision->payload;
    }

    /**
     * Revisions of an entity, newest first; ties keep insertion order reversed.
     *
     * @param string $type
     * @param string|int $id
     * @return Builder
     */
    protected function revisionsOf(string $type, string|int $id): Builder
    {
        return $this->model->newQuery()
            ->where('revisionable_type', $type)
            ->where('revisionable_id', (string) $id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
