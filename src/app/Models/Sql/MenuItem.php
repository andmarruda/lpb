<?php
namespace Andmarruda\Lpb\Models\Sql;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $table = 'lpb_menu_items';

    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'url',
        'target',
        'order',
    ];

    protected $attributes = [
        'target' => '_self',
        'order' => 0,
    ];

    protected $casts = [
        'menu_id' => 'integer',
        'parent_id' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Get the menu that owns the item.
     *
     * @return BelongsTo
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    /**
     * Get the parent item.
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * Get the child items.
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('order')->orderBy('id');
    }
}
