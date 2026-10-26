<?php

namespace Andmarruda\Lpb\Repositories\Concerns;

use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Illuminate\Database\Eloquent\Builder;

/**
 * Revision storage shared by both drivers; expects a $model property.
 */
trait ManagesRevisions
{
    /**
     * Record a snapshot of an entity.
     *
     * @param string $type Entity type, e.g. page or template
     * @param string|int $id Entity identifier
     * @param array $payload Full entity state at this moment
     * @param PublishState $state State the entity is in
     * @param string|null $createdBy Opaque user identifier
     * @return array The recorded revision as array
     */
    public function record(string $type, string|int $id, array $payload, PublishState $state, ?string $createdBy = null): array
    {
        if (trim($type) === '') {
            throw new InvalidValueException('Revision type must be a non-empty string.');
        }

        return $this->model->create([
            'revisionable_type' => $type,
            'revisionable_id' => (string) $id,
            'payload' => $payload,
            'status' => $state,
            'created_by' => $createdBy,
        ])->toArray();
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
