<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Tests\TestCase;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Models\Mongo\Template;
use Andmarruda\Lpb\Repositories\Mongo\PageTreeRepository;
use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;
use Andmarruda\Lpb\Tests\Feature\Concerns\TreeSaveScenario;

/**
 * MongoDB PageTreeRepository integration tests
 */
class PageTreeIntegrationTest extends TestCase
{
    use RegistersTestWidgets, TreeSaveScenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate();
        $this->registerTestWidgets();
    }

    protected function tearDown(): void
    {
        $this->truncate();

        parent::tearDown();
    }

    protected function truncate(): void
    {
        Page::truncate();
        Template::truncate();
    }

    /**
     * Test the container binds the MongoDB tree repository
     */
    public function test_container_binds_tree_repository(): void
    {
        $this->assertInstanceOf(PageTreeRepository::class, app(PageTreeRepositoryInterface::class));
    }

    protected function storeOutdatedCard(string $pageId, string $blockId, string $instanceId): void
    {
        $page = Page::find($pageId);
        $blocks = $page->blocks;
        $blocks[0]['widgets'][0]['schema_version'] = 1;
        $blocks[0]['widgets'][0]['config'] = ['heading' => 'Old'];
        $page->blocks = $blocks;
        $page->save();
    }
}
