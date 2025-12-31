<?php

namespace Andmarruda\Lpb\Repositories;

use Illuminate\Database\Eloquent\Model;
use Andmarruda\Lpb\Contracts\RepositoryInterface;

/**
 * Base repository implementation with common CRUD operations.
 *
 * This class provides default implementations for standard database operations
 * that work with both SQL (Eloquent) and MongoDB models. Concrete repositories
 * should extend this class and add specific methods as needed.
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * Create a new repository instance.
     *
     * @param Model $model The Eloquent or MongoDB model instance
     */
    public function __construct(
        protected Model $model
    ) {}

    /**
     * Find a record by its ID.
     *
     * @param string|int $id The record identifier
     * @return array|null The record data as array or null if not found
     */
    public function find(string|int $id): ?array
    {
        $record = $this->model->find($id);
        return $record ? $record->toArray() : null;
    }

    /**
     * Retrieve all records.
     *
     * @return array Collection of all records as array
     */
    public function all(): array
    {
        return $this->model->all()->toArray();
    }

    /**
     * Create a new record.
     *
     * @param array $data The data to create the record
     * @return array The created record as array
     */
    public function create(array $data): array
    {
        $record = $this->model->create($data);
        return $record->toArray();
    }

    /**
     * Update an existing record by its ID.
     *
     * @param string|int $id The record identifier
     * @param array $data The data to update
     * @return array|null The updated record as array or null if not found
     */
    public function update(string|int $id, array $data): ?array
    {
        $record = $this->model->find($id);
        if ($record) {
            $record->update($data);
            return $record->fresh()->toArray();
        }
        return null;
    }

    /**
     * Delete a record by its ID.
     *
     * @param string|int $id The record identifier
     * @return bool True if deleted successfully, false otherwise
     */
    public function delete(string|int $id): bool
    {
        $record = $this->model->find($id);
        if ($record) {
            return (bool) $record->delete();
        }
        return false;
    }
}
