<?php

namespace Andmarruda\Lpb\Repositories;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent repository implementation for SQL databases.
 *
 * This class extends the base repository to work specifically with Eloquent models.
 * All CRUD operations are inherited from BaseRepository.
 *
 * @deprecated Use BaseRepository directly or create specific repositories extending it
 */
class EloquentRepository extends BaseRepository
{
    /**
     * Create a new Eloquent repository instance.
     *
     * @param Model $model The Eloquent model instance
     */
    public function __construct(Model $model)
    {
        parent::__construct($model);
    }
}