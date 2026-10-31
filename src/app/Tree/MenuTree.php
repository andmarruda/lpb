<?php
namespace Andmarruda\Lpb\Tree;

/**
 * Nests flat menu items (id, parent_id, order) for delivery: each item keeps
 * id, label, url and target and gains its children, ordered by order then id.
 */
final class MenuTree
{
    /**
     * @param array $items
     * @return array
     */
    public static function nest(array $items): array
    {
        $ids = array_flip(array_map(fn (array $item) => (string) $item['id'], $items));
        usort($items, fn (array $a, array $b) => (($a['order'] ?? 0) <=> ($b['order'] ?? 0)) ?: strcmp((string) $a['id'], (string) $b['id']));

        $byParent = [];
        foreach ($items as $item) {
            $parent = $item['parent_id'] ?? null;
            $byParent[$parent !== null && isset($ids[(string) $parent]) ? (string) $parent : ''][] = $item;
        }
        return self::children($byParent, '');
    }

    private static function children(array $byParent, string $parent): array
    {
        return array_map(fn (array $item) => [
            'id' => $item['id'],
            'label' => $item['label'],
            'url' => $item['url'],
            'target' => $item['target'] ?? '_self',
            'children' => (string) $item['id'] === $parent ? [] : self::children($byParent, (string) $item['id']),
        ], $byParent[$parent] ?? []);
    }
}
