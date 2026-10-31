<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Repositories\Concerns\GuardsParents;
use Andmarruda\Lpb\Repositories\Concerns\ValidatesBlocks;
use Andmarruda\Lpb\Contracts\BlockRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Andmarruda\Lpb\Support\Ulid;

/**
 * MongoDB implementation of the Block repository.
 *
 * Blocks and their widget instances are embedded in the page document as a
 * nested tree (see EmbeddedBlocks). Writes edit a flat view of the tree and
 * store it back nested, incrementing the page's tree_version; concurrent
 * node-level writes to the same page can overwrite each other (saveTree is
 * the concurrency-safe way to edit a tree).
 */
class BlockRepository implements BlockRepositoryInterface
{
    use GuardsParents, ValidatesBlocks;

    /**
     * Create a new MongoDB block repository instance.
     *
     * @param Page $pages The MongoDB Page model instance
     */
    public function __construct(protected Page $pages)
    {
    }

    /**
     * Find a block with its widget instances.
     *
     * @param string|int $id The block identifier
     * @return array|null The block as array or null if not found
     */
    public function find(string|int $id): ?array
    {
        [$page, $blocks, $index] = $this->locate($id);
        return $page ? EmbeddedBlocks::present($this->pageId($page), $blocks[$index]) : null;
    }

    /**
     * Retrieve all blocks with their widget instances.
     *
     * @return array Blocks grouped by page, ordered by order and id
     */
    public function all(): array
    {
        return $this->pages->whereNotNull('blocks')->get()
            ->flatMap(fn (Page $page) => EmbeddedBlocks::presentAll($this->pageId($page), EmbeddedBlocks::flat($page)))
            ->all();
    }

    /**
     * Create a block on a page.
     *
     * @param array $data page_id and type are required; id (a client-generated ULID), parent_id, order and styles are optional
     * @return array The created block as array
     */
    public function create(array $data): array
    {
        $pageId = $data['page_id'] ?? null;
        $page = $pageId === null ? null : $this->pages->find($pageId);
        if (!$page) {
            throw new InvalidRelationException("Page '{$pageId}' not found.");
        }

        $attributes = $this->blockAttributes($data, true);
        $id = $this->newBlockId($data);
        $blocks = EmbeddedBlocks::flat($page);
        if (in_array($id, array_column($blocks, 'id'), true)) {
            throw new InvalidRelationException("Block '{$id}' already exists.");
        }

        $parentId = $attributes['parent_id'] ?? null;
        if ($parentId !== null) {
            $this->assertEmbeddedParent($blocks, $parentId, null, 'block');
        }

        $block = [
            'id' => $id,
            'parent_id' => $parentId,
            'type' => $attributes['type'],
            'order' => $attributes['order'] ?? 0,
            'styles' => $attributes['styles'],
            'widget_instances' => [],
        ];
        $blocks[] = $block;
        $this->assertWithinLimits($blocks);
        EmbeddedBlocks::store($page, $blocks);

        return EmbeddedBlocks::present($this->pageId($page), $block);
    }

    /**
     * Update the type, order, parent or styles of a block.
     *
     * @param string|int $id The block identifier
     * @param array $data The data to update; other keys are ignored
     * @return array|null The updated block as array or null if not found
     */
    public function update(string|int $id, array $data): ?array
    {
        [$page, $blocks, $index] = $this->locate($id);
        if (!$page) {
            return null;
        }

        $attributes = $this->blockAttributes($data, false);
        if (($attributes['parent_id'] ?? null) !== null) {
            $this->assertEmbeddedParent($blocks, $attributes['parent_id'], $blocks[$index]['id'], 'block');
        }

        $blocks[$index] = array_merge($blocks[$index], $attributes);
        $this->assertWithinLimits($blocks);
        EmbeddedBlocks::store($page, $blocks);

        return EmbeddedBlocks::present($this->pageId($page), $blocks[$index]);
    }

    /**
     * Delete a block and its widget instances; its children become root blocks.
     *
     * @param string|int $id The block identifier
     * @return bool False if the block was not found
     */
    public function delete(string|int $id): bool
    {
        [$page, $blocks, $index] = $this->locate($id);
        if (!$page) {
            return false;
        }

        $deleted = $blocks[$index]['id'];
        array_splice($blocks, $index, 1);
        foreach ($blocks as &$block) {
            if (($block['parent_id'] ?? null) === $deleted) {
                $block['parent_id'] = null;
            }
        }
        unset($block);

        EmbeddedBlocks::store($page, $blocks);
        return true;
    }

    /**
     * Retrieve the blocks of a page.
     *
     * @param string|int $pageId The page identifier
     * @return array Blocks ordered by order, then id
     */
    public function getForPage(string|int $pageId): array
    {
        $page = $this->pages->find($pageId);
        return $page ? EmbeddedBlocks::presentAll($this->pageId($page), EmbeddedBlocks::flat($page)) : [];
    }

    /**
     * Add a widget instance to a block.
     *
     * @param string|int $blockId The block identifier
     * @param string $widget Registered widget name
     * @param array $config Widget configuration
     * @param int $order Position inside the block
     * @param array $styles Style variants
     * @return array The created widget instance as array
     */
    public function addWidgetInstance(string|int $blockId, string $widget, array $config, int $order = 0, array $styles = []): array
    {
        [$page, $blocks, $index] = $this->locate($blockId);
        if (!$page) {
            throw new InvalidRelationException("Block '{$blockId}' not found.");
        }

        $instance = ['id' => Ulid::generate()]
            + $this->widgetInstanceAttributes($widget, $config, $styles)
            + ['order' => $this->validOrder($order)];
        $blocks[$index]['widget_instances'][] = $instance;
        $this->assertWithinLimits($blocks);
        EmbeddedBlocks::store($page, $blocks);

        return EmbeddedBlocks::presentWidgetInstance($blocks[$index]['id'], $instance);
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
        [$page, $blocks, $index, $instanceIndex] = $this->locateWidgetInstance($blockId, $widgetInstanceId);
        if (!$page) {
            return null;
        }

        $instance = &$blocks[$index]['widget_instances'][$instanceIndex];
        $instance = array_merge($instance, $this->widgetInstanceAttributes($instance['widget'], $config, null));
        unset($instance);
        EmbeddedBlocks::store($page, $blocks);

        return EmbeddedBlocks::presentWidgetInstance($blocks[$index]['id'], $blocks[$index]['widget_instances'][$instanceIndex]);
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
        [$page, $blocks, $index, $instanceIndex] = $this->locateWidgetInstance($blockId, $widgetInstanceId);
        if (!$page) {
            return false;
        }

        array_splice($blocks[$index]['widget_instances'], $instanceIndex, 1);
        EmbeddedBlocks::store($page, $blocks);
        return true;
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
        $page = $this->pages->find($pageId);
        if (!$page) {
            return false;
        }

        $positions = array_flip(array_map(fn ($id) => Ulid::normalize((string) $id), array_values($orderedBlockIds)));
        $blocks = EmbeddedBlocks::flat($page);
        if (count($positions) !== count($orderedBlockIds) || array_diff(array_keys($positions), array_column($blocks, 'id')) !== []) {
            return false;
        }

        foreach ($blocks as &$block) {
            if (isset($positions[$block['id']])) {
                $block['order'] = $positions[$block['id']];
            }
        }
        unset($block);

        EmbeddedBlocks::store($page, $blocks);
        return true;
    }

    /**
     * Find the page holding a block, its flat blocks and the block's index in them.
     *
     * @param string|int $blockId
     * @return array{0: Page|null, 1: array, 2: int|null}
     */
    protected function locate(string|int $blockId): array
    {
        $blockId = Ulid::normalize((string) $blockId);
        $page = $this->pages->where('block_ids', $blockId)->first();
        if (!$page) {
            return [null, [], null];
        }

        $blocks = EmbeddedBlocks::flat($page);
        $index = array_search($blockId, array_column($blocks, 'id'), true);
        return $index === false ? [null, [], null] : [$page, $blocks, $index];
    }

    /**
     * Find the page, flat blocks, block index and widget instance index of a widget instance.
     *
     * @param string|int $blockId
     * @param string|int $widgetInstanceId
     * @return array{0: Page|null, 1: array, 2: int|null, 3: int|null}
     */
    protected function locateWidgetInstance(string|int $blockId, string|int $widgetInstanceId): array
    {
        [$page, $blocks, $index] = $this->locate($blockId);
        if (!$page) {
            return [null, [], null, null];
        }

        $instanceIndex = array_search(
            Ulid::normalize((string) $widgetInstanceId),
            array_column($blocks[$index]['widget_instances'], 'id'),
            true
        );
        return $instanceIndex === false ? [null, [], null, null] : [$page, $blocks, $index, $instanceIndex];
    }

    protected function pageId(Page $page): string
    {
        return (string) $page->getKey();
    }
}
