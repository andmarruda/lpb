<?php

namespace App\Models;

use App\Enums\StatusType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LpbPage extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'title',
        'extra_css',
        'extra_js',
        'slug',
        'status',
        'theme',
    ];

    protected $casts = [
        'theme'  => 'boolean',
        'status' => StatusType::class,
    ];

    /**
     * Returns the metatags associated with the page
     * 
     * @return Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function metatags(): HasMany
    {
        return $this->hasMany(LpbMetatag::class, 'lpb_page_id');
    }

    /**
     * Returns the widgets associated with the page
     * 
     * @return Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(LpbPageWidget::class, 'lpb_page_id');
    }
}
