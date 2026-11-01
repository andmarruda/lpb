<?php
namespace Andmarruda\Lpb\Models\Sql;

use Andmarruda\Lpb\Models\Concerns\UsesConfiguredConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Base64 contents of a media record stored on the "database" disk.
 */
class MediaFile extends Model
{
    use UsesConfiguredConnection;

    protected $table = 'lpb_media_files';

    protected $primaryKey = 'media_id';

    public $incrementing = false;

    protected $fillable = ['media_id', 'path', 'contents'];
}
