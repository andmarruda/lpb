<?php
namespace Andmarruda\Lpb\Models\Sql;

use Andmarruda\Lpb\Models\Concerns\UsesConfiguredConnection;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use UsesConfiguredConnection;

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
