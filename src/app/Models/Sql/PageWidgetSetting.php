<?php
namespace Andmarruda\Lpb\Models\Sql;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageWidgetSetting extends Model
{
    protected $table = 'lpb_page_widget_settings';

    protected $fillable = [
        'key',
        'value',
        'lpb_page_widget_id',
    ];

    public function widget(): BelongsTo
    {
        return $this->belongsTo(PageWidget::class, 'lpb_page_widget_id');
    }
}
