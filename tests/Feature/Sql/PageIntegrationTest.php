<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Repositories\Sql\PageRepository;
use Andmarruda\Lpb\Models\Sql\Page;

/**
 * SQL PageRepository integration tests
 *
 * Tests the complete integration of PageRepository with SQL database.
 */
class PageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected PageRepository $repository;

    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PageRepository(new Page());
    }

    /**
     * Test create page with SQL database
     */
    public function test_create_page_with_sql_database(): void
    {
        $data = [
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'theme' => true
        ];

        $result = $this->repository->create($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('Test Page', $result['title']);
        $this->assertEquals('test-page', $result['slug']);

        $this->assertDatabaseHas('lpb_pages', [
            'slug' => 'test-page',
            'title' => 'Test Page'
        ]);
    }

    /**
     * Test find page by slug
     */
    public function test_find_page_by_slug(): void
    {
        $this->repository->create([
            'title' => 'About Us',
            'slug' => 'about-us',
            'status' => 'published'
        ]);

        $result = $this->repository->findBySlug('about-us');

        $this->assertNotNull($result);
        $this->assertEquals('About Us', $result['title']);
        $this->assertEquals('about-us', $result['slug']);
    }

    /**
     * Test get published pages only
     */
    public function test_get_published_pages_only(): void
    {
        $this->repository->create([
            'title' => 'Published Page',
            'slug' => 'published',
            'status' => 'published'
        ]);

        $this->repository->create([
            'title' => 'Draft Page',
            'slug' => 'draft',
            'status' => 'draft'
        ]);

        $published = $this->repository->getPublished();

        $this->assertIsArray($published);
        $this->assertCount(1, $published);
        $this->assertEquals('published', $published[0]['status']);
    }

    /**
     * Test update page
     */
    public function test_update_page(): void
    {
        $page = $this->repository->create([
            'title' => 'Original Title',
            'slug' => 'original',
            'status' => 'draft'
        ]);

        $updated = $this->repository->update($page['id'], [
            'title' => 'Updated Title',
            'status' => 'published'
        ]);

        $this->assertNotNull($updated);
        $this->assertEquals('Updated Title', $updated['title']);
        $this->assertEquals('published', $updated['status']);
    }

    /**
     * Test delete page
     */
    public function test_delete_page(): void
    {
        $page = $this->repository->create([
            'title' => 'To Delete',
            'slug' => 'to-delete',
            'status' => 'draft'
        ]);

        $result = $this->repository->delete($page['id']);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('lpb_pages', [
            'id' => $page['id']
        ]);
    }

    /**
     * Test get pages by status
     */
    public function test_get_pages_by_status(): void
    {
        $this->repository->create([
            'title' => 'Draft 1',
            'slug' => 'draft-1',
            'status' => 'draft'
        ]);

        $this->repository->create([
            'title' => 'Draft 2',
            'slug' => 'draft-2',
            'status' => 'draft'
        ]);

        $this->repository->create([
            'title' => 'Published',
            'slug' => 'published',
            'status' => 'published'
        ]);

        $drafts = $this->repository->getByStatus('draft');

        $this->assertCount(2, $drafts);
        $this->assertEquals('draft', $drafts[0]['status']);
        $this->assertEquals('draft', $drafts[1]['status']);
    }
}
