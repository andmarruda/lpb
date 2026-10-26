<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Repositories\Mongo\PageRepository;
use Andmarruda\Lpb\Models\Mongo\Page;

/**
 * MongoDB PageRepository integration tests
 *
 * Tests the complete integration of PageRepository with MongoDB database.
 */
class PageIntegrationTest extends TestCase
{
    protected PageRepository $repository;

    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        Page::truncate();

        $this->repository = new PageRepository(new Page());
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        Page::truncate();

        parent::tearDown();
    }

    /**
     * Test create page with MongoDB database
     */
    public function test_create_page_with_mongodb_database(): void
    {
        $data = [
            'title' => 'Test Page MongoDB',
            'slug' => 'test-page-mongo',
            'status' => 'published',
            'blocks' => [],
            'metatags' => []
        ];

        $result = $this->repository->create($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('Test Page MongoDB', $result['title']);
        $this->assertEquals('test-page-mongo', $result['slug']);
    }

    /**
     * Test find page by slug
     */
    public function test_find_page_by_slug(): void
    {
        $this->repository->create([
            'title' => 'About Us MongoDB',
            'slug' => 'about-us-mongo',
            'status' => 'published',
            'blocks' => [],
            'metatags' => []
        ]);

        $result = $this->repository->findBySlug('about-us-mongo');

        $this->assertNotNull($result);
        $this->assertEquals('About Us MongoDB', $result['title']);
        $this->assertEquals('about-us-mongo', $result['slug']);
    }

    /**
     * Test get published pages only
     */
    public function test_get_published_pages_only(): void
    {
        $this->repository->create([
            'title' => 'Published Page',
            'slug' => 'published-mongo',
            'status' => 'published',
            'blocks' => [],
            'metatags' => []
        ]);

        $this->repository->create([
            'title' => 'Draft Page',
            'slug' => 'draft-mongo',
            'status' => 'draft',
            'blocks' => [],
            'metatags' => []
        ]);

        $published = $this->repository->getPublished();

        $this->assertIsArray($published);
        $this->assertCount(1, $published);
        $this->assertEquals('published', $published[0]['status']);
    }

    /**
     * Test set metatag on page
     */
    public function test_set_metatag_on_page(): void
    {
        $page = $this->repository->create([
            'title' => 'Page',
            'slug' => 'page-metatag',
            'status' => 'published',
            'blocks' => [],
            'metatags' => []
        ]);

        $result = $this->repository->setMetatag(
            $page['id'],
            'description',
            'Test description for page'
        );

        $this->assertNotNull($result);
        $this->assertArrayHasKey('metatags', $result);
    }

    /**
     * Test get metatag from page
     */
    public function test_get_metatag_from_page(): void
    {
        $page = $this->repository->create([
            'title' => 'Page',
            'slug' => 'page-get-metatag',
            'status' => 'published',
            'blocks' => [],
            'metatags' => [
                ['name' => 'keywords', 'content' => 'laravel, mongodb, test']
            ]
        ]);

        $result = $this->repository->getMetatag($page['id'], 'keywords');

        $this->assertEquals('laravel, mongodb, test', $result);
    }

    /**
     * Test metatags are stored as embedded documents, not JSON strings
     */
    public function test_metatags_are_embedded_documents(): void
    {
        $page = $this->repository->create(['title' => 'Page', 'slug' => 'page-embedded', 'status' => 'draft']);

        $this->repository->setMetatag($page['id'], 'description', 'Embedded');

        $this->assertTrue(Page::where('metatags.name', 'description')->exists());
    }

    /**
     * Test delete page
     */
    public function test_delete_page(): void
    {
        $page = $this->repository->create([
            'title' => 'To Delete',
            'slug' => 'to-delete-mongo',
            'status' => 'draft',
            'blocks' => [],
            'metatags' => []
        ]);

        $result = $this->repository->delete($page['id']);

        $this->assertTrue($result);

        $found = $this->repository->find($page['id']);
        $this->assertNull($found);
    }

    /**
     * Test the container binds the MongoDB page repository
     */
    public function test_container_binds_mongo_page_repository(): void
    {
        $this->assertInstanceOf(PageRepository::class, app(PageRepositoryInterface::class));
    }

    /**
     * Test pages are stored in the lpb_pages collection
     */
    public function test_pages_are_stored_in_prefixed_collection(): void
    {
        $this->repository->create(['title' => 'Home', 'slug' => 'home-collection']);

        $this->assertSame(1, DB::connection('mongodb')->getDatabase()->selectCollection('lpb_pages')->countDocuments(['slug' => 'home-collection']));
    }
}
