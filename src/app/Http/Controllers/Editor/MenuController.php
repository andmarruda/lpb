<?php
namespace Andmarruda\Lpb\Http\Controllers\Editor;

use Andmarruda\Lpb\Contracts\MenuRepositoryInterface;
use Andmarruda\Lpb\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Editor API for menus and their items.
 */
class MenuController extends Controller
{
    public function __construct(private MenuRepositoryInterface $menus)
    {
    }

    public function index(): JsonResponse
    {
        $this->authorizeEdit();
        return new JsonResponse(['data' => $this->menus->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeEdit();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
        ]);
        return new JsonResponse($this->menus->create($data), 201);
    }

    /**
     * A menu with its items as a flat, ordered list.
     */
    public function show(string $menu): JsonResponse
    {
        $this->authorizeEdit();
        $found = $this->menus->withItems($menu);
        return $found ? new JsonResponse($found) : $this->notFound('Menu not found.');
    }

    public function update(Request $request, string $menu): JsonResponse
    {
        $this->authorizeEdit();
        $updated = $this->menus->update($menu, $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
        ]));
        return $updated ? new JsonResponse($updated) : $this->notFound('Menu not found.');
    }

    public function destroy(string $menu): JsonResponse
    {
        $this->authorizeEdit();
        return $this->menus->delete($menu) ? new JsonResponse(null, 204) : $this->notFound('Menu not found.');
    }

    public function storeItem(Request $request, string $menu): JsonResponse
    {
        $this->authorizeEdit();
        return new JsonResponse($this->menus->addItem($menu, $request->all()), 201);
    }

    public function updateItem(Request $request, string $menu, string $item): JsonResponse
    {
        $this->authorizeEdit();
        $updated = $this->menus->updateItem($menu, $item, $request->all());
        return $updated ? new JsonResponse($updated) : $this->notFound('Menu item not found.');
    }

    public function destroyItem(string $menu, string $item): JsonResponse
    {
        $this->authorizeEdit();
        return $this->menus->removeItem($menu, $item) ? new JsonResponse(null, 204) : $this->notFound('Menu item not found.');
    }

    /**
     * Body: {"items": [id, ...]} in the desired order.
     */
    public function reorderItems(Request $request, string $menu): JsonResponse
    {
        $this->authorizeEdit();
        $data = $request->validate(['items' => ['present', 'array']]);

        if (!$this->menus->reorderItems($menu, $data['items'])) {
            return new JsonResponse(['message' => 'The list must contain each item of the menu at most once.'], 422);
        }
        return new JsonResponse($this->menus->withItems($menu));
    }
}
