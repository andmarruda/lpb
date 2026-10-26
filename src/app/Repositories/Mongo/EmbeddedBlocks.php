<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

/**
 * Shapes blocks embedded in a page document like the SQL repository output:
 * ordered lists with page_id on blocks and block_id on widget instances.
 */
final class EmbeddedBlocks
{
    /**
     * @param string $pageId
     * @param array $blocks Raw embedded blocks
     * @return array Blocks ordered by order, then insertion
     */
    public static function presentAll(string $pageId, array $blocks): array
    {
        return array_map(fn (array $block) => self::present($pageId, $block), self::sortByOrder($blocks));
    }

    /**
     * @param string $pageId
     * @param array $block Raw embedded block
     * @return array
     */
    public static function present(string $pageId, array $block): array
    {
        return [
            'id' => $block['id'],
            'page_id' => $pageId,
            'parent_id' => $block['parent_id'] ?? null,
            'type' => $block['type'],
            'order' => $block['order'] ?? 0,
            'widget_instances' => array_map(
                fn (array $instance) => self::presentWidgetInstance($block['id'], $instance),
                self::sortByOrder($block['widget_instances'] ?? [])
            ),
        ];
    }

    /**
     * @param string $blockId
     * @param array $instance Raw embedded widget instance
     * @return array
     */
    public static function presentWidgetInstance(string $blockId, array $instance): array
    {
        return [
            'id' => $instance['id'],
            'block_id' => $blockId,
            'widget' => $instance['widget'],
            'config' => $instance['config'] ?? [],
            'order' => $instance['order'] ?? 0,
        ];
    }

    /**
     * Stable sort by order, so equal orders keep insertion order.
     *
     * @param array $items
     * @return array
     */
    private static function sortByOrder(array $items): array
    {
        $items = array_values($items);
        usort($items, fn (array $a, array $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        return $items;
    }
}
