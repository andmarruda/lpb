<?php

namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Models\Sql\Revision;
use Andmarruda\Lpb\Repositories\Concerns\ManagesRevisions;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;

/**
 * SQL implementation of the Revision repository.
 */
class RevisionRepository implements RevisionRepositoryInterface
{
    use ManagesRevisions;

    /**
     * Create a new SQL revision repository instance.
     *
     * @param Revision $model The SQL Revision model instance
     */
    public function __construct(protected Revision $model)
    {
    }
}
