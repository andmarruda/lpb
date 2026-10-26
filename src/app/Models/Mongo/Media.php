<?php
namespace Andmarruda\Lpb\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;

class Media extends Model
{
    protected $connection = 'mongodb';

    // $table instead of $collection: "collection" is also an attribute of this model.
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
