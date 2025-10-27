<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LpbPageWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'position_x',
        'position_y',
        'child_id',
        'widget',
        'lpb_page_id',
    ];

    protected $casts = [
        'position_x' => 'integer',
        'position_y' => 'integer',
    ];

    /**
     * Returns the page that widget belongs
     * 
     * @return Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(LpbPage::class, 'lpb_page_id');
    }

    /**
     * Returns the child widget, if any
     * 
     * @return Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(self::class, 'child_id');
    }

    /**
     * Returns the parent widget, if any
     * 
     * @return Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function parentOf(): HasOne
    {
        return $this->hasOne(self::class, 'child_id', 'id');
    }

    /**
     * Returns the settings of the widget
     * 
     * @return Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function settings(): HasMany
    {
        return $this->hasMany(LpbPageWidgetSetting::class, 'lpb_page_widget_id');
    }
}
