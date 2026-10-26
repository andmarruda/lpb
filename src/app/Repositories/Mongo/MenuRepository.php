<?php

namespace Andmarruda\Lpb\Repositories\Mongo;

use Andmarruda\Lpb\Models\Mongo\Menu;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Repositories\Concerns\GuardsParents;
use Andmarruda\Lpb\Repositories\Concerns\ValidatesMenuItems;
use Andmarruda\Lpb\Contracts\MenuRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Illuminate\Support\Str;

/**
 * MongoDB implementation of the Menu repository.
 *
 * Items are embedded in the menu document's items array as a flat list with
 * generated ids; nesting is expressed through parent_id, so the tree depth
 * is not limited by the document structure. Every item write rewrites the
 * menu's items array, so concurrent writes to the same menu can overwrite
 * each other.
 */
class MenuRepository extends BaseRepository implements MenuRepositoryInterface
{
    use GuardsParents, ValidatesMenuItems;

    /**
     * Create a new MongoDB menu repository instance.
     *
     * @param Menu $model The MongoDB Menu model instance
     */
    public function __construct(Menu $model)
    {
        parent::__construct($model);
    }

    /**
     * Find a menu by its identifier, without items.
     *
     * @param string|int $id The menu identifier
     * @return array|null The menu as array or null if not found
     */
    public function find(string|int $id): ?array
    {
        $menu = $this->model->find($id);
        return $menu ? $this->presentMenu($menu) : null;
    }

    /**
     * Retrieve all menus, without items.
     *
     * @return array Collection of menus as array
     */
    public function all(): array
    {
        return $this->model->all()->map(fn (Menu $menu) => $this->presentMenu($menu))->all();
    }

    /**
     * Create a menu. Items are added through addItem().
     *
     * @param array $data The menu data
     * @return array The created menu as array
     */
    public function create(array $data): array
    {
        return $this->presentMenu($this->model->create($data));
    }

    /**
     * Update a menu. Items are changed through the item methods.
     *
     * @param string|int $id The menu identifier
     * @param array $data The data to update
     * @return array|null The updated menu as array or null if not found
     */
    public function update(string|int $id, array $data): ?array
    {
        $menu = $this->model->find($id);
        if (!$menu) {
            return null;
        }

        $menu->update($data);
        return $this->presentMenu($menu->fresh());
    }

    /**
     * Find a menu by its slug.
     *
     * @param string $slug The menu slug
     * @return array|null The menu as array or null if not found
     */
    public function findBySlug(string $slug): ?array
    {
        $menu = $this->model->where('slug', $slug)->first();
        return $menu ? $this->presentMenu($menu) : null;
    }

    /**
     * Find the first menu created for a location.
     *
     * @param string $location The menu location
     * @return array|null The menu as array or null if not found
     */
    public function findByLocation(string $location): ?array
    {
        $menu = $this->model->where('location', $location)->orderBy('id')->first();
        return $menu ? $this->presentMenu($menu) : null;
    }

    /**
     * Find a menu with its items.
     *
     * @param string|int $id The menu identifier
     * @return array|null The menu with items as array or null if not found
     */
    public function withItems(string|int $id): ?array
    {
        $menu = $this->model->find($id);
        if (!$menu) {
            return null;
        }

        $menuId = (string) $menu->getKey();
        $items = array_values($menu->items ?? []);
        usort($items, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $this->presentMenu($menu) + [
            'items' => array_map(fn (array $item) => $this->presentItem($menuId, $item), $items),
        ];
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
        $items = $menu->items ?? [];
        $parentId = isset($attributes['parent_id']) ? (string) $attributes['parent_id'] : null;
        if ($parentId !== null) {
            $this->assertEmbeddedParent($items, $parentId, null, 'menu item');
        }

        $created = [
            'id' => $this->newId(),
            'parent_id' => $parentId,
            'label' => $attributes['label'],
            'url' => $attributes['url'],
            'target' => $attributes['target'] ?? '_self',
            'order' => $attributes['order'] ?? 0,
        ];
        $items[] = $created;
        $this->saveItems($menu, $items);

        return $this->presentItem((string) $menu->getKey(), $created);
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
        [$menu, $index] = $this->locateItem($menuId, $itemId);
        if (!$menu) {
            return null;
        }

        $attributes = $this->menuItemAttributes($data, false);
        $items = $menu->items;
        if (array_key_exists('parent_id', $attributes)) {
            $attributes['parent_id'] = $attributes['parent_id'] === null ? null : (string) $attributes['parent_id'];
            if ($attributes['parent_id'] !== null) {
                $this->assertEmbeddedParent($items, $attributes['parent_id'], (string) $itemId, 'menu item');
            }
        }

        $items[$index] = array_merge($items[$index], $attributes);
        $this->saveItems($menu, $items);

        return $this->presentItem((string) $menu->getKey(), $items[$index]);
    }

    /**
     * Remove an item from a menu; its children become root items.
     *
     * @param string|int $menuId The menu identifier
     * @param string|int $itemId The item identifier
     * @return bool False if the item was not found in the menu
     */
    public function removeItem(string|int $menuId, string|int $itemId): bool
    {
        [$menu, $index] = $this->locateItem($menuId, $itemId);
        if (!$menu) {
            return false;
        }

        $items = $menu->items;
        array_splice($items, $index, 1);
        foreach ($items as &$item) {
            if (($item['parent_id'] ?? null) === (string) $itemId) {
                $item['parent_id'] = null;
            }
        }
        unset($item);

        $this->saveItems($menu, $items);
        return true;
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
        $menu = $this->model->find($menuId);
        if (!$menu) {
            return false;
        }

        $positions = array_flip(array_map('strval', array_values($orderedItemIds)));
        $items = $menu->items ?? [];
        if (count($positions) !== count($orderedItemIds) || array_diff(array_keys($positions), array_column($items, 'id')) !== []) {
            return false;
        }

        foreach ($items as &$item) {
            if (isset($positions[$item['id']])) {
                $item['order'] = $positions[$item['id']];
            }
        }
        unset($item);

        $this->saveItems($menu, $items);
        return true;
    }

    /**
     * Find a menu and the index of one of its items.
     *
     * @param string|int $menuId
     * @param string|int $itemId
     * @return array{0: Menu|null, 1: int|null}
     */
    protected function locateItem(string|int $menuId, string|int $itemId): array
    {
        $menu = $this->model->find($menuId);
        $index = $menu ? array_search((string) $itemId, array_column($menu->items ?? [], 'id'), true) : false;
        return $index === false ? [null, null] : [$menu, $index];
    }

    /**
     * Menu fields without the embedded items.
     *
     * @param Menu $menu
     * @return array
     */
    protected function presentMenu(Menu $menu): array
    {
        $data = $menu->toArray();
        unset($data['items']);
        return $data;
    }

    /**
     * @param string $menuId
     * @param array $item Raw embedded item
     * @return array
     */
    protected function presentItem(string $menuId, array $item): array
    {
        return [
            'id' => $item['id'],
            'menu_id' => $menuId,
            'parent_id' => $item['parent_id'] ?? null,
            'label' => $item['label'],
            'url' => $item['url'],
            'target' => $item['target'] ?? '_self',
            'order' => $item['order'] ?? 0,
        ];
    }

    protected function saveItems(Menu $menu, array $items): void
    {
        $menu->items = array_values($items);
        $menu->save();
    }

    /**
     * Embedded ids are ULIDs: laravel-mongodb casts 24-hex strings queried on
     * "items._id" to ObjectId, which would never match an id stored as a string.
     *
     * @return string
     */
    protected function newId(): string
    {
        return (string) Str::ulid();
    }
}
