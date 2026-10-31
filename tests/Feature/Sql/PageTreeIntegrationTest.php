<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Contracts\PageTreeRepositoryInterface;
use Andmarruda\Lpb\Models\Sql\WidgetInstance;
use Andmarruda\Lpb\Repositories\Sql\PageTreeRepository;
use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;
use Andmarruda\Lpb\Tests\Feature\Concerns\TreeSaveScenario;

/**
 * SQL PageTreeRepository integration tests
 */
class PageTreeIntegrationTest extends TestCase
{
    use RefreshDatabase, RegistersTestWidgets, TreeSaveScenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerTestWidgets();
    }

    /**
     * Test the container binds the SQL tree repository
     */
    public function test_container_binds_tree_repository(): void
    {
        $this->assertInstanceOf(PageTreeRepository::class, app(PageTreeRepositoryInterface::class));
    }

    protected function storeOutdatedCard(string $pageId, string $blockId, string $instanceId): void
    {
        WidgetInstance::query()->whereKey($instanceId)->toBase()->update([
            'schema_version' => 1,
            'config' => json_encode(['heading' => 'Old']),
        ]);
    }
}
