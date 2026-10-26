<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

use Andmarruda\Lpb\Models\Mongo\Revision;
use Andmarruda\Lpb\Repositories\Concerns\ManagesRevisions;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;

/**
 * MongoDB implementation of the Revision repository.
 */
class RevisionRepository implements RevisionRepositoryInterface
{
    use ManagesRevisions;

    /**
     * Create a new MongoDB revision repository instance.
     *
     * @param Revision $model The MongoDB Revision model instance
     */
    public function __construct(protected Revision $model)
    {
    }
}
