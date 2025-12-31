<?php
namespace Andmarruda\Lpb\Models\Sql;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageWidget extends Model
{
    protected $table = 'lpb_page_widgets';

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
     * Get the page that owns the widget.
     * 
     * @return BelongsTo
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'lpb_page_id');
    }

    /**
     * Get the parent widget.
     * 
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PageWidget::class, 'child_id');
    }

    /**
     * Get the child widgets.
     * 
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(PageWidget::class, 'child_id');
    }

    /**
     * Get the settings for the widget.
     * 
     * @return HasMany
     */
    public function settings(): HasMany
    {
        return $this->hasMany(PageWidgetSetting::class, 'lpb_page_widget_id');
    }
}
