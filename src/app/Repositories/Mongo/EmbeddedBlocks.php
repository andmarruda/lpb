<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Tree\FlatTree;

/**
 * Blocks embedded in a page document.
 *
 * The page stores its blocks nested like the canonical tree (each block
 * embeds its widgets and children, with an order among its siblings) plus
 * block_ids, an indexed list of every block id in the tree. Repositories
 * edit a flat view of the tree and write it back nested; this class shapes
 * the flat view like the SQL repository output: ordered lists with page_id
 * on blocks and block_id on widget instances.
 */
final class EmbeddedBlocks
{
    /**
     * Flat view of a page's nested blocks.
     *
     * @param Page $page
     * @return array Flat blocks (id, parent_id, type, order, styles, widget_instances)
     */
    public static function flat(Page $page): array
    {
        return FlatTree::flatten($page->blocks ?? []);
    }

    /**
     * Store flat blocks as the page's nested tree and bump its tree_version.
     *
     * @param Page $page
     * @param array $blocks Flat blocks
     * @return void
     */
    public static function store(Page $page, array $blocks): void
    {
        self::storeNested($page, FlatTree::nest(array_values($blocks), withOrder: true));
    }

    /**
     * Store a nested tree as the page's blocks and bump its tree_version.
     *
     * @param Page $page
     * @param array $blocks Nested blocks
     * @return void
     */
    public static function storeNested(Page $page, array $blocks): void
    {
        $page->blocks = $blocks;
        $page->block_ids = FlatTree::blockIds($blocks);
        $page->tree_version = ($page->tree_version ?? 0) + 1;
        $page->save();
    }

    /**
     * @param string $pageId
     * @param array $blocks Flat blocks
     * @return array Blocks ordered by order, then id
     */
    public static function presentAll(string $pageId, array $blocks): array
    {
        return array_map(fn (array $block) => self::present($pageId, $block), self::sortByOrder($blocks));
    }

    /**
     * @param string $pageId
     * @param array $block Flat block
     * @return array
     */
    public static function present(string $pageId, array $block): array
    {
        return [
            'id' => (string) $block['id'],
            'page_id' => $pageId,
            'parent_id' => $block['parent_id'] ?? null,
            'type' => $block['type'],
            'order' => $block['order'] ?? 0,
            'styles' => $block['styles'] ?? [],
            'widget_instances' => array_map(
                fn (array $instance) => self::presentWidgetInstance((string) $block['id'], $instance),
                self::sortByOrder($block['widget_instances'] ?? [])
            ),
        ];
    }

    /**
     * @param string $blockId
     * @param array $instance Flat widget instance
     * @return array
     */
    public static function presentWidgetInstance(string $blockId, array $instance): array
    {
        return [
            'id' => (string) $instance['id'],
            'block_id' => $blockId,
            'widget' => $instance['widget'],
            'schema_version' => $instance['schema_version'] ?? 1,
            'config' => $instance['config'] ?? [],
            'styles' => $instance['styles'] ?? [],
            'order' => $instance['order'] ?? 0,
        ];
    }

    /**
     * Sort by order, then id, like the SQL repositories (ULIDs follow insertion).
     *
     * @param array $items
     * @return array
     */
    private static function sortByOrder(array $items): array
    {
        $items = array_values($items);
        usort($items, fn (array $a, array $b) => (($a['order'] ?? 0) <=> ($b['order'] ?? 0)) ?: strcmp((string) $a['id'], (string) $b['id']));
        return $items;
    }
}
