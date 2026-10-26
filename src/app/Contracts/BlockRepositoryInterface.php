<?php
namespace Andmarruda\Lpb\Contracts;

/**
 * Layout blocks of a page and the widget instances they hold.
 *
 * Block arrays carry id, page_id, parent_id, type, order and an ordered
 * widget_instances list; widget instance arrays carry id, block_id, widget,
 * config and order. create() requires page_id and type; parent_id must point
 * to a block of the same page and must not create a cycle.
 */
interface BlockRepositoryInterface extends RepositoryInterface
{
    /**
     * Blocks of a page as a flat list ordered by order, then insertion.
     *
     * @param string|int $pageId
     * @return array Empty if the page has no blocks or does not exist
     */
    public function getForPage(string|int $pageId): array;

    /**
     * @param string|int $blockId
     * @param string $widget Widget class name or registered alias
     * @param array $config
     * @param int $order
     * @return array The created widget instance
     * @throws \Andmarruda\Lpb\Exceptions\InvalidRelationException If the block does not exist
     */
    public function addWidgetInstance(string|int $blockId, string $widget, array $config, int $order = 0): array;

    /**
     * Replace the config of a widget instance.
     *
     * @param string|int $blockId
     * @param string|int $widgetInstanceId
     * @param array $config
     * @return array|null The updated widget instance or null if not found in the block
     */
    public function updateWidgetInstance(string|int $blockId, string|int $widgetInstanceId, array $config): ?array;

    /**
     * @param string|int $blockId
     * @param string|int $widgetInstanceId
     * @return bool False if the widget instance was not found in the block
     */
    public function removeWidgetInstance(string|int $blockId, string|int $widgetInstanceId): bool;

    /**
     * Set each listed block's order to its position in the list.
     *
     * @param string|int $pageId
     * @param array $orderedBlockIds
     * @return bool False, with nothing changed, if an id repeats or is not a block of the page
     */
    public function reorder(string|int $pageId, array $orderedBlockIds): bool;
}
