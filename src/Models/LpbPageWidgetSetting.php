<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LpbPageWidgetSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'lpb_page_widget_id',
    ];

    /**
     * Returns the widget that setting belongs
     * 
     * @return Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function widget(): BelongsTo
    {
        return $this->belongsTo(LpbPageWidget::class, 'lpb_page_widget_id');
    }
}
