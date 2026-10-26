<?php
namespace Andmarruda\Lpb\Models\Sql;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Andmarruda\Lpb\ValueObjects\PublishState;

class Revision extends Model
{
    const UPDATED_AT = null;

    protected $table = 'lpb_revisions';

    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'payload',
        'status',
        'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
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
