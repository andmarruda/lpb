<?php

namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Models\Sql\Block;
use Andmarruda\Lpb\Models\Sql\Page;
use Andmarruda\Lpb\Models\Sql\WidgetInstance;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Repositories\Concerns\GuardsParents;
use Andmarruda\Lpb\Repositories\Concerns\ValidatesBlocks;
use Andmarruda\Lpb\Contracts\BlockRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidRelationException;

/**
 * SQL implementation of the Block repository.
 *
 * Deleting a block cascades to its widget instances and turns its children
 * into root blocks through the foreign keys.
 */
class BlockRepository extends BaseRepository implements BlockRepositoryInterface
{
    use GuardsParents, ValidatesBlocks;

    /**
     * Create a new SQL block repository instance.
     *
     * @param Block $model The SQL Block model instance
     * @param Page $pages The SQL Page model instance
     * @param WidgetInstance $widgetInstances The SQL WidgetInstance model instance
     */
    public function __construct(Block $model, protected Page $pages, protected WidgetInstance $widgetInstances)
    {
        parent::__construct($model);
    }

    /**
     * Find a block with its widget instances.
     *
     * @param string|int $id The block identifier
     * @return array|null The block as array or null if not found
     */
    public function find(string|int $id): ?array
    {
        return $this->model->with('widgetInstances')->find($id)?->toArray();
    }

    /**
     * Retrieve all blocks with their widget instances.
     *
     * @return array Blocks ordered by page, order and insertion
     */
    public function all(): array
    {
        return $this->model->with('widgetInstances')
            ->orderBy('page_id')->orderBy('order')->orderBy('id')
            ->get()->toArray();
    }

    /**
     * Create a block on a page.
     *
     * @param array $data page_id and type are required; parent_id and order are optional
     * @return array The created block as array
     */
    public function create(array $data): array
    {
        $pageId = $data['page_id'] ?? null;
        if ($pageId === null || !$this->pages->whereKey($pageId)->exists()) {
            throw new InvalidRelationException("Page '{$pageId}' not found.");
        }

        $attributes = $this->blockAttributes($data, true);
        if (($attributes['parent_id'] ?? null) !== null) {
            $this->assertParentRecord($this->model, 'page_id', $pageId, $attributes['parent_id'], null, 'block');
        }

        $block = $this->model->create(['page_id' => $pageId] + $attributes);
        return $this->find($block->getKey());
    }

    /**
     * Update the type, order or parent of a block.
     *
     * @param string|int $id The block identifier
     * @param array $data The data to update; other keys are ignored
     * @return array|null The updated block as array or null if not found
     */
    public function update(string|int $id, array $data): ?array
    {
        $block = $this->model->find($id);
        if (!$block) {
            return null;
        }

        $attributes = $this->blockAttributes($data, false);
        if (($attributes['parent_id'] ?? null) !== null) {
            $this->assertParentRecord($this->model, 'page_id', $block->page_id, $attributes['parent_id'], $block->getKey(), 'block');
        }

        $block->update($attributes);
        return $this->find($id);
    }

    /**
     * Retrieve the blocks of a page.
     *
     * @param string|int $pageId The page identifier
     * @return array Blocks ordered by order, then insertion
     */
    public function getForPage(string|int $pageId): array
    {
        return $this->model->with('widgetInstances')
            ->where('page_id', $pageId)
            ->orderBy('order')->orderBy('id')
            ->get()->toArray();
    }

    /**
     * Add a widget instance to a block.
     *
     * @param string|int $blockId The block identifier
     * @param string $widget Widget class name or registered alias
     * @param array $config Widget configuration
     * @param int $order Position inside the block
     * @return array The created widget instance as array
     */
    public function addWidgetInstance(string|int $blockId, string $widget, array $config, int $order = 0): array
    {
        $block = $this->model->find($blockId) ?? throw new InvalidRelationException("Block '{$blockId}' not found.");
        $this->assertWidget($widget);

        $instance = $block->widgetInstances()->create([
            'widget' => $widget,
            'config' => $config,
            'order' => $this->validOrder($order),
        ]);

        return $instance->fresh()->toArray();
    }

    /**
     * Replace the config of a widget instance.
     *
     * @param string|int $blockId The block identifier
     * @param string|int $widgetInstanceId The widget instance identifier
     * @param array $config The new configuration
     * @return array|null The updated widget instance or null if not found in the block
     */
    public function updateWidgetInstance(string|int $blockId, string|int $widgetInstanceId, array $config): ?array
    {
        $instance = $this->widgetInstances->where('block_id', $blockId)->find($widgetInstanceId);
        if (!$instance) {
            return null;
        }

        $instance->setConfig($config);
        $instance->save();
        return $instance->fresh()->toArray();
    }

    /**
     * Remove a widget instance from a block.
     *
     * @param string|int $blockId The block identifier
     * @param string|int $widgetInstanceId The widget instance identifier
     * @return bool False if the widget instance was not found in the block
     */
    public function removeWidgetInstance(string|int $blockId, string|int $widgetInstanceId): bool
    {
        return (bool) $this->widgetInstances->where('block_id', $blockId)->whereKey($widgetInstanceId)->delete();
    }

    /**
     * Set each listed block's order to its position in the list.
     *
     * @param string|int $pageId The page identifier
     * @param array $orderedBlockIds Block identifiers in the desired order
     * @return bool False, with nothing changed, if the page is missing or an id repeats or is not a block of the page
     */
    public function reorder(string|int $pageId, array $orderedBlockIds): bool
    {
        if (!$this->pages->whereKey($pageId)->exists()) {
            return false;
        }

        $ids = array_values($orderedBlockIds);
        if (count($ids) !== count(array_unique(array_map('strval', $ids)))) {
            return false;
        }

        if ($this->model->where('page_id', $pageId)->whereKey($ids)->count() !== count($ids)) {
            return false;
        }

        $this->model->getConnection()->transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                $this->model->whereKey($id)->update(['order' => $position]);
            }
        });

        return true;
    }
}
