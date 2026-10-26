<?php
namespace Andmarruda\Lpb\Models\Mongo;

use Andmarruda\Lpb\Models\Concerns\UsesConfiguredConnection;
use MongoDB\Laravel\Eloquent\Model;
use LogicException;
use Andmarruda\Lpb\ValueObjects\PublishState;

class Revision extends Model
{
    use UsesConfiguredConnection;

    const UPDATED_AT = null;

    protected $connection = 'mongodb';
    protected $table = 'lpb_revisions';

    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'payload',
        'status',
        'created_by',
    ];

    // payload has no cast so it is stored as an embedded document.
    protected $casts = [
        'status' => PublishState::class,
    ];

    /**
     * Revisions are append-only.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Revisions are append-only and cannot be updated.'));
        static::deleting(fn () => throw new LogicException('Revisions are append-only and cannot be deleted.'));
    }
}
