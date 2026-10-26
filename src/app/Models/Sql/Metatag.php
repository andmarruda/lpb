<?php
namespace Andmarruda\Lpb\Models\Sql;

use Andmarruda\Lpb\Models\Concerns\UsesConfiguredConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Metatag extends Model
{
    use UsesConfiguredConnection;

    protected $table = 'lpb_metatags';

    protected $fillable = [
        'name',
        'content',
        'lpb_page_id',
    ];

    /**
     * Get the page that owns the metatag.
     * 
     * @return BelongsTo
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'lpb_page_id');
    }
}