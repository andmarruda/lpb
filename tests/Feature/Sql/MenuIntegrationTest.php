<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Contracts\MenuRepositoryInterface;
use Andmarruda\Lpb\Repositories\Sql\MenuRepository;
use Andmarruda\Lpb\Models\Sql\Menu;
use Andmarruda\Lpb\Models\Sql\MenuItem;
use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Andmarruda\Lpb\Exceptions\InvalidValueException;

/**
 * SQL MenuRepository integration tests
 */
class MenuIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected MenuRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MenuRepository(new Menu(), new MenuItem());
    }

    protected function createMenu(array $overrides = []): array
    {
        return $this->repository->create(array_merge(['name' => 'Main', 'slug' => 'main', 'location' => 'header'], $overrides));
    }

    /**
     * Test the container binds the SQL menu repository
     */
    public function test_container_binds_menu_repository(): void
    {
        $this->assertInstanceOf(MenuRepository::class, app(MenuRepositoryInterface::class));
    }

    /**
     * Test find by slug and location without items
     */
    public function test_find_by_slug_and_location(): void
    {
        $main = $this->createMenu();
        $this->createMenu(['name' => 'Secondary', 'slug' => 'secondary']);
        $this->createMenu(['name' => 'Footer', 'slug' => 'footer', 'location' => 'footer']);
        $this->repository->addItem($main['id'], ['label' => 'Home', 'url' => '/']);

        $this->assertSame($main['id'], $this->repository->findBySlug('main')['id']);
        $this->assertSame($main['id'], $this->repository->findByLocation('header')['id']);
        $this->assertSame('footer', $this->repository->findByLocation('footer')['slug']);
        $this->assertArrayNotHasKey('items', $this->repository->findBySlug('main'));
        $this->assertArrayNotHasKey('items', $this->repository->find($main['id']));
        $this->assertNull($this->repository->findBySlug('missing'));
        $this->assertNull($this->repository->findByLocation('sidebar'));
    }

    /**
     * Test an invalid slug is rejected
     */
    public function test_rejects_invalid_slug(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->createMenu(['slug' => 'Main Menu']);
    }

    /**
     * Test add item applies defaults and withItems returns ordered items
     */
    public function test_add_items_and_with_items(): void
    {
        $menu = $this->createMenu();

        $contact = $this->repository->addItem($menu['id'], ['label' => 'Contact', 'url' => '/contact', 'order' => 2]);
        $this->repository->addItem($menu['id'], ['label' => 'Home', 'url' => '/', 'order' => 0]);
        $this->repository->addItem($menu['id'], ['label' => 'Blog', 'url' => 'https://blog.example.com', 'target' => '_blank', 'order' => 0]);

        $this->assertSame($menu['id'], $contact['menu_id']);
        $this->assertNull($contact['parent_id']);
        $this->assertSame('_self', $contact['target']);

        $result = $this->repository->withItems($menu['id']);
        $this->assertSame(['Home', 'Blog', 'Contact'], array_column($result['items'], 'label'));
        $this->assertSame('_blank', $result['items'][1]['target']);
        $this->assertNull($this->repository->withItems(999999));
    }

    /**
     * Test item validation
     */
    public function test_rejects_invalid_items(): void
    {
        $menu = $this->createMenu();

        foreach ([['url' => '/'], ['label' => 'Home'], ['label' => 'Home', 'url' => '/', 'target' => 'popup'], ['label' => 'Home', 'url' => '/', 'order' => -1]] as $item) {
            try {
                $this->repository->addItem($menu['id'], $item);
                $this->fail('Invalid item was accepted: ' . json_encode($item));
            } catch (InvalidValueException) {
            }
        }

        $this->expectException(InvalidRelationException::class);
        $this->repository->addItem(999999, ['label' => 'Home', 'url' => '/']);
    }

    /**
     * Test nested items, parents from other menus and cycles
     */
    public function test_nested_items(): void
    {
        $menu = $this->createMenu();
        $other = $this->createMenu(['slug' => 'other']);
        $about = $this->repository->addItem($menu['id'], ['label' => 'About', 'url' => '/about']);
        $team = $this->repository->addItem($menu['id'], ['label' => 'Team', 'url' => '/about/team', 'parent_id' => $about['id']]);
        $foreign = $this->repository->addItem($other['id'], ['label' => 'Elsewhere', 'url' => '/x']);

        $this->assertSame($about['id'], $team['parent_id']);

        try {
            $this->repository->addItem($menu['id'], ['label' => 'Bad', 'url' => '/bad', 'parent_id' => $foreign['id']]);
            $this->fail('Parent from another menu was accepted');
        } catch (InvalidRelationException) {
        }

        $this->expectException(InvalidRelationException::class);
        $this->repository->updateItem($menu['id'], $about['id'], ['parent_id' => $team['id']]);
    }

    /**
     * Test update and remove items
     */
    public function test_update_and_remove_item(): void
    {
        $menu = $this->createMenu();
        $about = $this->repository->addItem($menu['id'], ['label' => 'About', 'url' => '/about']);
        $team = $this->repository->addItem($menu['id'], ['label' => 'Team', 'url' => '/team', 'parent_id' => $about['id']]);

        $updated = $this->repository->updateItem($menu['id'], $about['id'], ['label' => 'About us', 'target' => '_blank', 'menu_id' => 0]);
        $this->assertSame('About us', $updated['label']);
        $this->assertSame('_blank', $updated['target']);
        $this->assertSame($menu['id'], $updated['menu_id']);

        $this->assertTrue($this->repository->removeItem($menu['id'], $about['id']));
        $items = $this->repository->withItems($menu['id'])['items'];
        $this->assertSame([$team['id']], array_column($items, 'id'));
        $this->assertNull($items[0]['parent_id']);

        $this->assertFalse($this->repository->removeItem($menu['id'], $about['id']));
        $this->assertNull($this->repository->updateItem($menu['id'], $about['id'], ['label' => 'x']));
    }

    /**
     * Test items are scoped to their menu
     */
    public function test_items_are_scoped_to_menu(): void
    {
        $menu = $this->createMenu();
        $other = $this->createMenu(['slug' => 'other']);
        $item = $this->repository->addItem($menu['id'], ['label' => 'Home', 'url' => '/']);

        $this->assertNull($this->repository->updateItem($other['id'], $item['id'], ['label' => 'x']));
        $this->assertFalse($this->repository->removeItem($other['id'], $item['id']));
    }

    /**
     * Test reorderItems sets orders by position and rejects invalid lists atomically
     */
    public function test_reorder_items(): void
    {
        $menu = $this->createMenu();
        $a = $this->repository->addItem($menu['id'], ['label' => 'A', 'url' => '/a']);
        $b = $this->repository->addItem($menu['id'], ['label' => 'B', 'url' => '/b']);
        $c = $this->repository->addItem($menu['id'], ['label' => 'C', 'url' => '/c']);
        $foreign = $this->repository->addItem($this->createMenu(['slug' => 'other'])['id'], ['label' => 'X', 'url' => '/x']);

        $this->assertTrue($this->repository->reorderItems($menu['id'], [$c['id'], $a['id'], $b['id']]));
        $this->assertSame(['C', 'A', 'B'], array_column($this->repository->withItems($menu['id'])['items'], 'label'));

        $this->assertFalse($this->repository->reorderItems($menu['id'], [$a['id'], $a['id']]));
        $this->assertFalse($this->repository->reorderItems($menu['id'], [$b['id'], $foreign['id']]));
        $this->assertFalse($this->repository->reorderItems(999999, []));
        $this->assertSame(['C', 'A', 'B'], array_column($this->repository->withItems($menu['id'])['items'], 'label'));
    }

    /**
     * Test deleting a menu removes its items
     */
    public function test_delete_menu(): void
    {
        $menu = $this->createMenu();
        $item = $this->repository->addItem($menu['id'], ['label' => 'Home', 'url' => '/']);

        $this->assertTrue($this->repository->delete($menu['id']));

        $this->assertNull($this->repository->find($menu['id']));
        $this->assertFalse($this->repository->removeItem($menu['id'], $item['id']));
    }
}
