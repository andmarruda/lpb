<?php
namespace Andmarruda\Lpb\Models\Concerns;

use Andmarruda\Lpb\Support\Ulid;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * ULID primary key, generated when the id is not given (client-generated
 * ids are kept) and stored uppercase like the MongoDB embedded ids.
 */
trait HasUlidKey
{
    use HasUlids;

    public function newUniqueId(): string
    {
        return Ulid::generate();
    }
}
