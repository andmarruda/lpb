<?php

namespace Andmarruda\Lpb\Repositories;

use MongoDB\Laravel\Eloquent\Model;

/**
 * MongoDB repository implementation for NoSQL databases.
 *
 * This class extends the base repository to work specifically with MongoDB models.
 * All CRUD operations are inherited from BaseRepository.
 *
 * @deprecated Use BaseRepository directly or create specific repositories extending it
 */
class MongoRepository extends BaseRepository
{
    /**
     * Create a new MongoDB repository instance.
     *
     * @param Model $model The MongoDB model instance
     */
    public function __construct(Model $model)
    {
        parent::__construct($model);
    }
}