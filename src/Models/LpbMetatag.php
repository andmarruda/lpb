<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LpbMetatag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'content',
        'property',
        'lpb_page_id',
    ];

    /**
     * Returns the page that metatag belongs
     * 
     * @return Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(LpbPage::class, 'lpb_page_id');
    }
}
