<?php

namespace Andmarruda\Lpb\Repositories\Sql;

use Andmarruda\Lpb\Models\Sql\Menu;
use Andmarruda\Lpb\Models\Sql\MenuItem;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Repositories\Concerns\GuardsParents;
use Andmarruda\Lpb\Repositories\Concerns\ValidatesMenuItems;
use Andmarruda\Lpb\Contracts\MenuRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidRelationException;

/**
 * SQL implementation of the Menu repository.
 *
 * Deleting a menu cascades to its items; deleting an item turns its
 * children into root items through the foreign keys.
 */
class MenuRepository extends BaseRepository implements MenuRepositoryInterface
{
    use GuardsParents, ValidatesMenuItems;

    /**
     * Create a new SQL menu repository instance.
     *
     * @param Menu $model The SQL Menu model instance
     * @param MenuItem $items The SQL MenuItem model instance
     */
    public function __construct(Menu $model, protected MenuItem $items)
    {
        parent::__construct($model);
    }

    /**
     * Find a menu by its slug.
     *
     * @param string $slug The menu slug
     * @return array|null The menu as array or null if not found
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->model->where('slug', $slug)->first()?->toArray();
    }

    /**
     * Find the first menu created for a location.
     *
     * @param string $location The menu location
     * @return array|null The menu as array or null if not found
     */
    public function findByLocation(string $location): ?array
    {
        return $this->model->where('location', $location)->orderBy('id')->first()?->toArray();
    }

    /**
     * Find a menu with its items eager loaded.
     *
     * @param string|int $id The menu identifier
     * @return array|null The menu with items as array or null if not found
     */
    public function withItems(string|int $id): ?array
    {
        return $this->model->with('items')->find($id)?->toArray();
    }

    /**
     * Add an item to a menu.
     *
     * @param string|int $menuId The menu identifier
     * @param array $item label and url are required; target, order and parent_id are optional
     * @return array The created item as array
     */
    public function addItem(string|int $menuId, array $item): array
    {
        $menu = $this->model->find($menuId) ?? throw new InvalidRelationException("Menu '{$menuId}' not found.");

        $attributes = $this->menuItemAttributes($item, true);
        if (($attributes['parent_id'] ?? null) !== null) {
            $this->assertParentRecord($this->items, 'menu_id', $menu->getKey(), $attributes['parent_id'], null, 'menu item');
        }

        return $menu->items()->create($attributes)->fresh()->toArray();
    }

    /**
     * Update an item of a menu.
     *
     * @param string|int $menuId The menu identifier
     * @param string|int $itemId The item identifier
     * @param array $data The data to update; other keys are ignored
     * @return array|null The updated item or null if not found in the menu
     */
    public function updateItem(string|int $menuId, string|int $itemId, array $data): ?array
    {
        $item = $this->items->where('menu_id', $menuId)->find($itemId);
        if (!$item) {
            return null;
        }

        $attributes = $this->menuItemAttributes($data, false);
        if (($attributes['parent_id'] ?? null) !== null) {
            $this->assertParentRecord($this->items, 'menu_id', $item->menu_id, $attributes['parent_id'], $item->getKey(), 'menu item');
        }

        $item->update($attributes);
        return $item->fresh()->toArray();
    }

    /**
     * Remove an item from a menu.
     *
     * @param string|int $menuId The menu identifier
     * @param string|int $itemId The item identifier
     * @return bool False if the item was not found in the menu
     */
    public function removeItem(string|int $menuId, string|int $itemId): bool
    {
        return (bool) $this->items->where('menu_id', $menuId)->whereKey($itemId)->delete();
    }

    /**
     * Set each listed item's order to its position in the list.
     *
     * @param string|int $menuId The menu identifier
     * @param array $orderedItemIds Item identifiers in the desired order
     * @return bool False, with nothing changed, if the menu is missing or an id repeats or is not an item of the menu
     */
    public function reorderItems(string|int $menuId, array $orderedItemIds): bool
    {
        if (!$this->model->whereKey($menuId)->exists()) {
            return false;
        }

        $ids = array_values($orderedItemIds);
        if (count($ids) !== count(array_unique(array_map('strval', $ids)))) {
            return false;
        }

        if ($this->items->where('menu_id', $menuId)->whereKey($ids)->count() !== count($ids)) {
            return false;
        }

        $this->items->getConnection()->transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                $this->items->whereKey($id)->update(['order' => $position]);
            }
        });

        return true;
    }
}
