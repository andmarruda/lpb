<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Andmarruda\Lpb\Contracts\PageContentRepositoryInterface;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Repositories\Sql\PageRepository;
use Andmarruda\Lpb\Models\Sql\Page;
use Andmarruda\Lpb\Models\Sql\PageWidget;

/**
 * SQL page content (widgets and metatags) integration tests
 *
 * Also guards against regressions of the Phase 1 Milestone 0 fixes.
 */
class PageContentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected PageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PageRepository(new Page());
    }

    protected function createPage(): array
    {
        return $this->repository->create([
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'draft',
        ]);
    }

    /**
     * Test the container binds the SQL repository for both page contracts
     */
    public function test_container_binds_sql_repositories(): void
    {
        $this->assertInstanceOf(PageRepository::class, app(PageRepositoryInterface::class));
        $this->assertInstanceOf(PageRepository::class, app(PageContentRepositoryInterface::class));
    }

    /**
     * Test widgets relationship uses lpb_page_id and orders by the order column
     */
    public function test_with_widgets_returns_widgets_ordered(): void
    {
        $page = $this->createPage();

        $this->repository->addWidget($page['id'], ['widget' => 'footer', 'order' => 2]);
        $this->repository->addWidget($page['id'], ['widget' => 'hero', 'order' => 1]);

        $result = $this->repository->withWidgets($page['id']);

        $this->assertSame(['hero', 'footer'], array_column($result['widgets'], 'widget'));
    }

    /**
     * Test widgets can be nested through parent_id
     */
    public function test_widget_parent_and_children(): void
    {
        $page = $this->createPage();

        $result = $this->repository->addWidget($page['id'], ['widget' => 'row']);
        $parentId = $result['widgets'][0]['id'];
        $this->repository->addWidget($page['id'], ['widget' => 'column', 'parent_id' => $parentId]);

        $parent = PageWidget::find($parentId);

        $this->assertSame(['column'], $parent->children->pluck('widget')->all());
        $this->assertSame('row', $parent->children->first()->parent->widget);
    }

    /**
     * Test add, update and remove widget with settings
     */
    public function test_widget_lifecycle_with_settings(): void
    {
        $page = $this->createPage();

        $result = $this->repository->addWidget($page['id'], [
            'widget' => 'hero',
            'settings' => [['key' => 'title', 'value' => 'Welcome']],
        ]);
        $widgetId = $result['widgets'][0]['id'];

        $this->assertSame('Welcome', $result['widgets'][0]['settings'][0]['value']);

        $result = $this->repository->updateWidget($page['id'], $widgetId, [
            'position_x' => 3,
            'settings' => [['key' => 'subtitle', 'value' => 'Hi']],
        ]);

        $this->assertSame(3, $result['widgets'][0]['position_x']);
        $this->assertSame(['subtitle'], array_column($result['widgets'][0]['settings'], 'key'));

        $result = $this->repository->removeWidget($page['id'], $widgetId);

        $this->assertSame([], $result['widgets']);
        $this->assertDatabaseMissing('lpb_page_widget_settings', ['lpb_page_widget_id' => $widgetId]);
    }

    /**
     * Test widget mutations return null for a missing page
     */
    public function test_widget_mutations_return_null_when_page_not_found(): void
    {
        $missing = '00000000-0000-0000-0000-000000000000';

        $this->assertNull($this->repository->addWidget($missing, ['widget' => 'hero']));
        $this->assertNull($this->repository->updateWidget($missing, 1, []));
        $this->assertNull($this->repository->removeWidget($missing, 1));
        $this->assertNull($this->repository->setMetatag($missing, 'description', 'x'));
        $this->assertNull($this->repository->getMetatag($missing, 'description'));
    }

    /**
     * Test metatags relationship resolves the Metatag model
     */
    public function test_set_and_get_metatag(): void
    {
        $page = $this->createPage();

        $this->repository->setMetatag($page['id'], 'description', 'First');
        $result = $this->repository->setMetatag($page['id'], 'description', 'Second');

        $this->assertCount(1, $result['metatags']);
        $this->assertSame('Second', $this->repository->getMetatag($page['id'], 'description'));
        $this->assertNull($this->repository->getMetatag($page['id'], 'keywords'));

        $withMetatags = $this->repository->withMetatags($page['id']);
        $this->assertSame('description', $withMetatags['metatags'][0]['name']);
    }

    /**
     * Test the widget settings migration rollback drops its own table
     */
    public function test_widget_settings_migration_rollback_drops_table(): void
    {
        $migration = require database_path('migrations/2025_10_26_102800_lpb_page_widgets_settings.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('lpb_page_widget_settings'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('lpb_page_widget_settings'));
    }
}
