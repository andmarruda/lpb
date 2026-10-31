<?php
namespace Andmarruda\Lpb\Tree;

/**
 * Conversion between flat blocks and the nested tree.
 *
 * Flat blocks carry id, parent_id, type, order, styles and a
 * widget_instances list (id, widget, schema_version, config, styles,
 * order), as the block repositories return them. Nested blocks carry id,
 * type, styles, a widgets list and a children list, ordered by position.
 */
final class FlatTree
{
    /**
     * Nest flat blocks; siblings and widgets are sorted by order, then input order.
     *
     * Blocks whose parent is missing become root blocks; blocks caught in a
     * cycle are unreachable and left out.
     *
     * @param array $blocks Flat blocks
     * @param bool $withOrder Keep each node's order (MongoDB storage) instead of dropping it
     * @return array Nested blocks
     */
    public static function nest(array $blocks, bool $withOrder = false): array
    {
        $ids = array_flip(array_map(fn (array $block) => (string) $block['id'], $blocks));
        $byParent = [];
        foreach (self::sortByOrder($blocks) as $block) {
            $parent = $block['parent_id'] ?? null;
            $parent = $parent !== null && isset($ids[(string) $parent]) ? (string) $parent : '';
            $byParent[$parent][] = $block;
        }

        return self::children($byParent, '', $withOrder);
    }

    /**
     * Flatten nested blocks; a node without an order gets its position.
     *
     * @param array $blocks Nested blocks
     * @param string|null $parentId
     * @return array Flat blocks, parents before their children
     */
    public static function flatten(array $blocks, ?string $parentId = null): array
    {
        $flat = [];
        foreach (array_values($blocks) as $position => $block) {
            $widgets = [];
            foreach (array_values($block['widgets'] ?? []) as $widgetPosition => $widget) {
                $widgets[] = [
                    'id' => (string) $widget['id'],
                    'widget' => $widget['widget'],
                    'schema_version' => $widget['schema_version'] ?? 1,
                    'config' => $widget['config'] ?? [],
                    'styles' => $widget['styles'] ?? [],
                    'order' => $widget['order'] ?? $widgetPosition,
                ];
            }

            $flat[] = [
                'id' => (string) $block['id'],
                'parent_id' => $parentId,
                'type' => $block['type'],
                'order' => $block['order'] ?? $position,
                'styles' => $block['styles'] ?? [],
                'widget_instances' => $widgets,
            ];
            array_push($flat, ...self::flatten($block['children'] ?? [], (string) $block['id']));
        }

        return $flat;
    }

    /**
     * Block levels of a nested tree; 0 when empty.
     *
     * @param array $blocks
     * @return int
     */
    public static function depth(array $blocks): int
    {
        $depth = 0;
        foreach ($blocks as $block) {
            $depth = max($depth, 1 + self::depth($block['children'] ?? []));
        }
        return $depth;
    }

    /**
     * Blocks plus widget instances of a nested tree.
     *
     * @param array $blocks
     * @return int
     */
    public static function count(array $blocks): int
    {
        $count = 0;
        foreach ($blocks as $block) {
            $count += 1 + count($block['widgets'] ?? []) + self::count($block['children'] ?? []);
        }
        return $count;
    }

    /**
     * Ids of every block of a nested tree.
     *
     * @param array $blocks
     * @return string[]
     */
    public static function blockIds(array $blocks): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            $ids[] = (string) $block['id'];
            array_push($ids, ...self::blockIds($block['children'] ?? []));
        }
        return $ids;
    }

    /**
     * @param array<string, array> $byParent
     * @param string $parent
     * @param bool $withOrder
     * @return array
     */
    private static function children(array $byParent, string $parent, bool $withOrder): array
    {
        $nodes = [];
        foreach ($byParent[$parent] ?? [] as $block) {
            $id = (string) $block['id'];
            $widgets = array_map(function (array $instance) use ($withOrder) {
                $widget = [
                    'id' => (string) $instance['id'],
                    'widget' => $instance['widget'],
                    'schema_version' => $instance['schema_version'] ?? 1,
                    'config' => $instance['config'] ?? [],
                    'styles' => $instance['styles'] ?? [],
                ];
                return $withOrder ? $widget + ['order' => $instance['order'] ?? 0] : $widget;
            }, self::sortByOrder($block['widget_instances'] ?? []));

            $node = [
                'id' => $id,
                'type' => $block['type'],
                'styles' => $block['styles'] ?? [],
                'widgets' => $widgets,
                // Guard against a node listed as its own parent
                'children' => $id === $parent ? [] : self::children($byParent, $id, $withOrder),
            ];
            $nodes[] = $withOrder ? ['order' => $block['order'] ?? 0] + $node : $node;
        }
        return $nodes;
    }

    /**
     * Stable sort by order, so equal orders keep input order.
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
