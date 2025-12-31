<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Tests\TestCase;
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
            'theme' => true,
            'widgets' => [],
            'metatags' => []
        ];

        $result = $this->repository->create($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('_id', $result);
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
            'widgets' => [],
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
            'widgets' => [],
            'metatags' => []
        ]);

        $this->repository->create([
            'title' => 'Draft Page',
            'slug' => 'draft-mongo',
            'status' => 'draft',
            'widgets' => [],
            'metatags' => []
        ]);

        $published = $this->repository->getPublished();

        $this->assertIsArray($published);
        $this->assertCount(1, $published);
        $this->assertEquals('published', $published[0]['status']);
    }

    /**
     * Test add widget to page
     */
    public function test_add_widget_to_page(): void
    {
        $page = $this->repository->create([
            'title' => 'Page with Widget',
            'slug' => 'page-widget',
            'status' => 'published',
            'widgets' => [],
            'metatags' => []
        ]);

        $widget = [
            'widget' => 'hero',
            'position_x' => 0,
            'position_y' => 0,
            'settings' => [
                ['key' => 'title', 'value' => 'Welcome']
            ]
        ];

        $result = $this->repository->addWidget($page['_id'], $widget);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('widgets', $result);
        $this->assertCount(1, $result['widgets']);
        $this->assertEquals('hero', $result['widgets'][0]['widget']);
    }

    /**
     * Test update widget on page
     */
    public function test_update_widget_on_page(): void
    {
        $page = $this->repository->create([
            'title' => 'Page',
            'slug' => 'page-update-widget',
            'status' => 'published',
            'widgets' => [
                ['widget' => 'hero', 'position_x' => 0, 'position_y' => 0]
            ],
            'metatags' => []
        ]);

        $result = $this->repository->updateWidget($page['_id'], 0, [
            'position_x' => 1,
            'position_y' => 2
        ]);

        $this->assertNotNull($result);
    }

    /**
     * Test remove widget from page
     */
    public function test_remove_widget_from_page(): void
    {
        $page = $this->repository->create([
            'title' => 'Page',
            'slug' => 'page-remove-widget',
            'status' => 'published',
            'widgets' => [
                ['widget' => 'hero', 'position_x' => 0],
                ['widget' => 'text', 'position_x' => 1]
            ],
            'metatags' => []
        ]);

        $result = $this->repository->removeWidget($page['_id'], 0);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('widgets', $result);
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
            'widgets' => [],
            'metatags' => []
        ]);

        $result = $this->repository->setMetatag(
            $page['_id'],
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
            'widgets' => [],
            'metatags' => [
                ['name' => 'keywords', 'content' => 'laravel, mongodb, test']
            ]
        ]);

        $result = $this->repository->getMetatag($page['_id'], 'keywords');

        $this->assertEquals('laravel, mongodb, test', $result);
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
            'widgets' => [],
            'metatags' => []
        ]);

        $result = $this->repository->delete($page['_id']);

        $this->assertTrue($result);

        $found = $this->repository->find($page['_id']);
        $this->assertNull($found);
    }
}
