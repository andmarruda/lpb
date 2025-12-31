<?php
namespace Andmarruda\Lpb\Contracts;

interface RepositoryInterface
{
    /**
     * Find a record by its ID.
     * 
     * @param string|int $id
     * @return array|null
     */
    public function find(string|int $id): ?array;

    /**
     * Retrieve all records.
     * 
     * @return array
     */
    public function all(): array;

    /**
     * Create a new record.
     * 
     * @param array $data
     */
    public function create(array $data): array;

    /**
     * Update an existing record by its ID.
     * 
     * @param string|int $id
     * @param array $data
     * @return array|null
     */
    public function update(string|int $id, array $data): ?array;

    /**
     * Delete a record by its ID.
     * 
     * @param string|int $id
     * @return bool
     */
    public function delete(string|int $id): bool;
}