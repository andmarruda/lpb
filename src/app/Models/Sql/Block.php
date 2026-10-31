<?php
namespace Andmarruda\Lpb\Models\Sql;

use Andmarruda\Lpb\Models\Concerns\HasUlidKey;
use Andmarruda\Lpb\Models\Concerns\UsesConfiguredConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Block extends Model
{
    use HasUlidKey, UsesConfiguredConnection;

    protected $table = 'lpb_blocks';

    protected $fillable = [
        'id',
        'page_id',
        'parent_id',
        'type',
        'order',
        'styles',
    ];

    protected $attributes = [
        'order' => 0,
    ];

    protected $casts = [
        'order' => 'integer',
        'styles' => 'array',
    ];

    /**
     * Get the page that owns the block.
     *
     * @return BelongsTo
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    /**
     * Get the parent block.
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'parent_id');
    }

    /**
     * Get the child blocks.
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(Block::class, 'parent_id')->orderBy('order')->orderBy('id');
    }

    /**
     * Get the widget instances of the block.
     *
     * @return HasMany
     */
    public function widgetInstances(): HasMany
    {
        return $this->hasMany(WidgetInstance::class, 'block_id')->orderBy('order')->orderBy('id');
    }
}
