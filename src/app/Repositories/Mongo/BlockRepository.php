<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Repositories\Concerns\GuardsParents;
use Andmarruda\Lpb\Repositories\Concerns\ValidatesBlocks;
use Andmarruda\Lpb\Contracts\BlockRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Illuminate\Support\Str;

/**
 * MongoDB implementation of the Block repository.
 *
 * Blocks and their widget instances are embedded in the page document's
 * blocks array, each with a generated id; children reference their parent
 * through parent_id. Every write rewrites the page's blocks array, so
 * concurrent writes to the same page can overwrite each other.
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
        [$page, $index] = $this->locate($id);
        return $page ? EmbeddedBlocks::present($this->pageId($page), $page->blocks[$index]) : null;
    }

    /**
     * Retrieve all blocks with their widget instances.
     *
     * @return array Blocks grouped by page, ordered by order and insertion
     */
    public function all(): array
    {
        return $this->pages->whereNotNull('blocks')->get()
            ->flatMap(fn (Page $page) => EmbeddedBlocks::presentAll($this->pageId($page), $page->blocks ?? []))
            ->all();
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
        $page = $pageId === null ? null : $this->pages->find($pageId);
        if (!$page) {
            throw new InvalidRelationException("Page '{$pageId}' not found.");
        }

        $attributes = $this->blockAttributes($data, true);
        $blocks = $page->blocks ?? [];
        $parentId = isset($attributes['parent_id']) ? (string) $attributes['parent_id'] : null;
        if ($parentId !== null) {
            $this->assertEmbeddedParent($blocks, $parentId, null, 'block');
        }

        $block = [
            'id' => $this->newId(),
            'parent_id' => $parentId,
            'type' => $attributes['type'],
            'order' => $attributes['order'] ?? 0,
            'widget_instances' => [],
        ];
        $blocks[] = $block;
        $this->saveBlocks($page, $blocks);

        return EmbeddedBlocks::present($this->pageId($page), $block);
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
        [$page, $index] = $this->locate($id);
        if (!$page) {
            return null;
        }

        $attributes = $this->blockAttributes($data, false);
        $blocks = $page->blocks;
        if (array_key_exists('parent_id', $attributes)) {
            $attributes['parent_id'] = $attributes['parent_id'] === null ? null : (string) $attributes['parent_id'];
            if ($attributes['parent_id'] !== null) {
                $this->assertEmbeddedParent($blocks, $attributes['parent_id'], (string) $id, 'block');
            }
        }

        $blocks[$index] = array_merge($blocks[$index], $attributes);
        $this->saveBlocks($page, $blocks);

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
        [$page, $index] = $this->locate($id);
        if (!$page) {
            return false;
        }

        $blocks = $page->blocks;
        array_splice($blocks, $index, 1);
        foreach ($blocks as &$block) {
            if (($block['parent_id'] ?? null) === (string) $id) {
                $block['parent_id'] = null;
            }
        }
        unset($block);

        $this->saveBlocks($page, $blocks);
        return true;
    }

    /**
     * Retrieve the blocks of a page.
     *
     * @param string|int $pageId The page identifier
     * @return array Blocks ordered by order, then insertion
     */
    public function getForPage(string|int $pageId): array
    {
        $page = $this->pages->find($pageId);
        return $page ? EmbeddedBlocks::presentAll($this->pageId($page), $page->blocks ?? []) : [];
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
        [$page, $index] = $this->locate($blockId);
        if (!$page) {
            throw new InvalidRelationException("Block '{$blockId}' not found.");
        }
        $this->assertWidget($widget);

        $instance = [
            'id' => $this->newId(),
            'widget' => $widget,
            'config' => $config,
            'order' => $this->validOrder($order),
        ];
        $blocks = $page->blocks;
        $blocks[$index]['widget_instances'][] = $instance;
        $this->saveBlocks($page, $blocks);

        return EmbeddedBlocks::presentWidgetInstance((string) $blockId, $instance);
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
        [$page, $index, $instanceIndex] = $this->locateWidgetInstance($blockId, $widgetInstanceId);
        if (!$page) {
            return null;
        }

        $blocks = $page->blocks;
        $blocks[$index]['widget_instances'][$instanceIndex]['config'] = $config;
        $this->saveBlocks($page, $blocks);

        return EmbeddedBlocks::presentWidgetInstance((string) $blockId, $blocks[$index]['widget_instances'][$instanceIndex]);
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
        [$page, $index, $instanceIndex] = $this->locateWidgetInstance($blockId, $widgetInstanceId);
        if (!$page) {
            return false;
        }

        $blocks = $page->blocks;
        array_splice($blocks[$index]['widget_instances'], $instanceIndex, 1);
        $this->saveBlocks($page, $blocks);
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

        $positions = array_flip(array_map('strval', array_values($orderedBlockIds)));
        $blocks = $page->blocks ?? [];
        $known = array_column($blocks, 'id');
        if (count($positions) !== count($orderedBlockIds) || array_diff(array_keys($positions), $known) !== []) {
            return false;
        }

        foreach ($blocks as &$block) {
            if (isset($positions[$block['id']])) {
                $block['order'] = $positions[$block['id']];
            }
        }
        unset($block);

        $this->saveBlocks($page, $blocks);
        return true;
    }

    /**
     * Find the page holding a block and the block's index in its blocks array.
     *
     * @param string|int $blockId
     * @return array{0: Page|null, 1: int|null}
     */
    protected function locate(string|int $blockId): array
    {
        $page = $this->pages->where('blocks.id', (string) $blockId)->first();
        $index = $page ? array_search((string) $blockId, array_column($page->blocks, 'id'), true) : false;
        return $index === false ? [null, null] : [$page, $index];
    }

    /**
     * Find the page, block index and widget instance index of a widget instance.
     *
     * @param string|int $blockId
     * @param string|int $widgetInstanceId
     * @return array{0: Page|null, 1: int|null, 2: int|null}
     */
    protected function locateWidgetInstance(string|int $blockId, string|int $widgetInstanceId): array
    {
        [$page, $index] = $this->locate($blockId);
        if (!$page) {
            return [null, null, null];
        }

        $instanceIndex = array_search(
            (string) $widgetInstanceId,
            array_column($page->blocks[$index]['widget_instances'] ?? [], 'id'),
            true
        );
        return $instanceIndex === false ? [null, null, null] : [$page, $index, $instanceIndex];
    }

    protected function saveBlocks(Page $page, array $blocks): void
    {
        $page->blocks = array_values($blocks);
        $page->save();
    }

    protected function pageId(Page $page): string
    {
        return (string) $page->getKey();
    }

    /**
     * Embedded ids are ULIDs: laravel-mongodb casts 24-hex strings queried on
     * "blocks._id" to ObjectId, which would never match an id stored as a string.
     *
     * @return string
     */
    protected function newId(): string
    {
        return (string) Str::ulid();
    }
}
