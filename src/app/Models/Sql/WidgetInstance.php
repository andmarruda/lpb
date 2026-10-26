<?php
namespace Andmarruda\Lpb\Models\Sql;

use Andmarruda\Lpb\Models\Concerns\UsesConfiguredConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetInstance extends Model
{
    use UsesConfiguredConnection;

    protected $table = 'lpb_widget_instances';

    protected $fillable = [
        'block_id',
        'widget',
        'config',
        'order',
    ];

    protected $attributes = [
        'order' => 0,
    ];

    protected $casts = [
        'block_id' => 'integer',
        'config' => 'array',
        'order' => 'integer',
    ];

    /**
     * Get the block that owns the widget instance.
     *
     * @return BelongsTo
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_id');
    }

    /**
     * Get the decoded widget configuration.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config ?? [];
    }

    /**
     * Replace the widget configuration.
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
}
