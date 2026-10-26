<?php
namespace Andmarruda\Lpb\Models\Sql;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
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

    protected $casts = [
        'size' => 'integer',
        'meta' => 'array',
    ];
}
