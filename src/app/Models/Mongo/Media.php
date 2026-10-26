<?php
namespace Andmarruda\Lpb\Models\Mongo;

use Andmarruda\Lpb\Models\Concerns\UsesConfiguredConnection;
use MongoDB\Laravel\Eloquent\Model;

class Media extends Model
{
    use UsesConfiguredConnection;

    protected $connection = 'mongodb';
    protected $table = 'lpb_media';

    protected $fillable = [
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'meta',
        'collection',
    ];

    // meta has no cast so it is stored as an embedded document.
    protected $casts = [
        'size' => 'integer',
    ];
}
