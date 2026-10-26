<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Repositories\Sql\PageRepository;
use Andmarruda\Lpb\Models\Sql\Page;

/**
 * SQL page metatag integration tests
 */
class PageMetatagIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected PageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PageRepository(new Page());
    }

    /**
     * Test the container binds the SQL page repository
     */
    public function test_container_binds_sql_page_repository(): void
    {
        $this->assertInstanceOf(PageRepository::class, app(PageRepositoryInterface::class));
    }

    /**
     * Test metatags are upserted by name and read back
     */
    public function test_set_and_get_metatag(): void
    {
        $page = $this->repository->create(['title' => 'Home', 'slug' => 'home', 'status' => 'draft']);

        $this->repository->setMetatag($page['id'], 'description', 'First');
        $result = $this->repository->setMetatag($page['id'], 'description', 'Second');

        $this->assertCount(1, $result['metatags']);
        $this->assertSame('Second', $this->repository->getMetatag($page['id'], 'description'));
        $this->assertNull($this->repository->getMetatag($page['id'], 'keywords'));

        $withMetatags = $this->repository->withMetatags($page['id']);
        $this->assertSame('description', $withMetatags['metatags'][0]['name']);
    }

    /**
     * Test metatag methods return null for a missing page
     */
    public function test_metatags_return_null_when_page_not_found(): void
    {
        $missing = '00000000-0000-0000-0000-000000000000';

        $this->assertNull($this->repository->setMetatag($missing, 'description', 'x'));
        $this->assertNull($this->repository->getMetatag($missing, 'description'));
    }
}
