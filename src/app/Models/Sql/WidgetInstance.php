<?php
namespace Andmarruda\Lpb\Models\Sql;

use Andmarruda\Lpb\Models\Concerns\HasUlidKey;
use Andmarruda\Lpb\Models\Concerns\UsesConfiguredConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetInstance extends Model
{
    use HasUlidKey, UsesConfiguredConnection;

    protected $table = 'lpb_widget_instances';

    protected $fillable = [
        'id',
        'block_id',
        'widget',
        'schema_version',
        'config',
        'styles',
        'order',
    ];

    protected $attributes = [
        'order' => 0,
        'schema_version' => 1,
    ];

    protected $casts = [
        'schema_version' => 'integer',
        'config' => 'array',
        'styles' => 'array',
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
