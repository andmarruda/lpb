<?php
namespace Andmarruda\Lpb\Models\Mongo;

use Andmarruda\Lpb\Models\Concerns\UsesConfiguredConnection;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Base64 contents of a media record stored on the "database" disk. A
 * document holds at most 16 MB, so keep media.max_size below ~12 MB.
 */
class MediaFile extends Model
{
    use UsesConfiguredConnection;

    protected $connection = 'mongodb';
    protected $table = 'lpb_media_files';

    protected $fillable = ['media_id', 'contents'];
}
