<?php
namespace Andmarruda\Lpb\Contracts;

/**
 * Navigation menus and their items.
 *
 * Menu arrays carry id, name, slug and location; withItems() adds an
 * items list. Item arrays carry id, menu_id, parent_id, label, url, target
 * and order. Items form a tree through parent_id, which must point to an
 * item of the same menu and must not create a cycle.
 */
interface MenuRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?array;

    /**
     * The first menu created for a location.
     *
     * @param string $location e.g. header, footer, sidebar
     * @return array|null
     */
    public function findByLocation(string $location): ?array;

    /**
     * A menu with its items as a flat list ordered by order, then insertion.
     *
     * @param string|int $id
     * @return array|null
     */
    public function withItems(string|int $id): ?array;

    /**
     * @param string|int $menuId
     * @param array $item label and url are required; target, order and parent_id are optional
     * @return array The created item
     * @throws \Andmarruda\Lpb\Exceptions\InvalidRelationException If the menu does not exist
     */
    public function addItem(string|int $menuId, array $item): array;

    /**
     * @param string|int $menuId
     * @param string|int $itemId
     * @param array $data
     * @return array|null The updated item or null if not found in the menu
     */
    public function updateItem(string|int $menuId, string|int $itemId, array $data): ?array;

    /**
     * Remove an item; its children become root items.
     *
     * @param string|int $menuId
     * @param string|int $itemId
     * @return bool False if the item was not found in the menu
     */
    public function removeItem(string|int $menuId, string|int $itemId): bool;

    /**
     * Set each listed item's order to its position in the list.
     *
     * @param string|int $menuId
     * @param array $orderedItemIds
     * @return bool False, with nothing changed, if the menu is missing or an id repeats or is not an item of the menu
     */
    public function reorderItems(string|int $menuId, array $orderedItemIds): bool;
}
