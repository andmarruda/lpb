<?php

namespace Andmarruda\Lpb\Tests\Unit\Repositories\Sql;

use PHPUnit\Framework\TestCase;
use Mockery;
use Andmarruda\Lpb\Repositories\Sql\PageRepository;
use Andmarruda\Lpb\Models\Sql\Page;

/**
 * Unit tests for SQL PageRepository
 *
 * This test suite validates the behavior of PageRepository
 * for SQL databases using Mockery for mocking dependencies.
 */
class PageRepositoryTest extends TestCase
{
    protected PageRepository $repository;
    protected $modelMock;

    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->modelMock = Mockery::mock(Page::class);
        $this->repository = new PageRepository($this->modelMock);
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test find method returns array when page exists
     *
     * This test verifies that the find method returns page data as an array
     * when the page exists in the repository.
     */
    public function test_find_returns_array_when_page_exists(): void
    {
        $id = '9d4e6f8a-1234-5678-9abc-def012345678';
        $expectedData = [
            'id' => $id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published'
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($pageMock);

        $result = $this->repository->find($id);

        $this->assertEquals($expectedData, $result);
    }

    /**
     * Test find method returns null when page not found
     *
     * This test verifies that the find method returns null when
     * the requested page does not exist.
     */
    public function test_find_returns_null_when_page_not_found(): void
    {
        $id = 'non-existent-id';

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn(null);

        $result = $this->repository->find($id);

        $this->assertNull($result);
    }

    /**
     * Test create method creates new page
     *
     * This test verifies that the create method correctly passes
     * data to the model and returns the created page.
     */
    public function test_create_creates_new_page(): void
    {
        $data = [
            'title' => 'New Page',
            'slug' => 'new-page',
            'status' => 'draft'
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn(array_merge(['id' => 'new-id'], $data));

        $this->modelMock->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($pageMock);

        $result = $this->repository->create($data);

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals($data['title'], $result['title']);
    }

    /**
     * Test update method updates existing page
     *
     * This test verifies that the update method finds and updates
     * the page with the provided data.
     */
    public function test_update_updates_existing_page(): void
    {
        $id = 'test-id';
        $data = ['title' => 'Updated Page Title'];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('update')
            ->once()
            ->with($data);

        $pageMock->shouldReceive('fresh')
            ->once()
            ->andReturnSelf();

        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn(array_merge(['id' => $id], $data));

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($pageMock);

        $result = $this->repository->update($id, $data);

        $this->assertNotNull($result);
        $this->assertEquals($data['title'], $result['title']);
    }

    /**
     * Test update method returns null when page not found
     *
     * This test verifies that the update method returns null
     * when trying to update a non-existent page.
     */
    public function test_update_returns_null_when_page_not_found(): void
    {
        $id = 'non-existent-id';
        $data = ['title' => 'Updated Title'];

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn(null);

        $result = $this->repository->update($id, $data);

        $this->assertNull($result);
    }

    /**
     * Test delete method deletes page
     *
     * This test verifies that the delete method successfully
     * removes the page and returns true.
     */
    public function test_delete_deletes_page(): void
    {
        $id = 'test-id';

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($pageMock);

        $result = $this->repository->delete($id);

        $this->assertTrue($result);
    }

    /**
     * Test delete method returns false when page not found
     *
     * This test verifies that the delete method returns false
     * when trying to delete a non-existent page.
     */
    public function test_delete_returns_false_when_page_not_found(): void
    {
        $id = 'non-existent-id';

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn(null);

        $result = $this->repository->delete($id);

        $this->assertFalse($result);
    }

    /**
     * Test findBySlug uses bySlug scope
     *
     * This test verifies that the findBySlug method uses the bySlug
     * scope to find pages by their slug.
     */
    public function test_find_by_slug_uses_by_slug_scope(): void
    {
        $slug = 'test-page';
        $expectedData = [
            'id' => '9d4e6f8a-1234-5678-9abc-def012345678',
            'slug' => $slug,
            'title' => 'Test Page'
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('first')
            ->once()
            ->andReturn($pageMock);

        $this->modelMock->shouldReceive('bySlug')
            ->once()
            ->with($slug)
            ->andReturn($queryMock);

        $result = $this->repository->findBySlug($slug);

        $this->assertEquals($expectedData, $result);
    }

    /**
     * Test findBySlug returns array when found
     *
     * This test verifies that findBySlug returns page data as an array
     * when a page with the given slug exists.
     */
    public function test_find_by_slug_returns_array_when_found(): void
    {
        $slug = 'about-us';
        $expectedData = [
            'id' => '1',
            'slug' => $slug,
            'title' => 'About Us',
            'status' => 'published'
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('first')
            ->once()
            ->andReturn($pageMock);

        $this->modelMock->shouldReceive('bySlug')
            ->once()
            ->with($slug)
            ->andReturn($queryMock);

        $result = $this->repository->findBySlug($slug);

        $this->assertIsArray($result);
        $this->assertEquals($slug, $result['slug']);
    }

    /**
     * Test findBySlug returns null when not found
     *
     * This test verifies that findBySlug returns null when no page
     * with the given slug exists.
     */
    public function test_find_by_slug_returns_null_when_not_found(): void
    {
        $slug = 'non-existent-slug';

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('first')
            ->once()
            ->andReturn(null);

        $this->modelMock->shouldReceive('bySlug')
            ->once()
            ->with($slug)
            ->andReturn($queryMock);

        $result = $this->repository->findBySlug($slug);

        $this->assertNull($result);
    }

    /**
     * Test getPublished uses published scope
     *
     * This test verifies that the getPublished method uses the published
     * scope to retrieve only published pages.
     */
    public function test_get_published_uses_published_scope(): void
    {
        $expectedData = [
            ['id' => '1', 'status' => 'published', 'title' => 'Page 1'],
            ['id' => '2', 'status' => 'published', 'title' => 'Page 2'],
        ];

        $collectionMock = Mockery::mock();
        $collectionMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('get')
            ->once()
            ->andReturn($collectionMock);

        $this->modelMock->shouldReceive('published')
            ->once()
            ->andReturn($queryMock);

        $result = $this->repository->getPublished();

        $this->assertEquals($expectedData, $result);
    }

    /**
     * Test getPublished returns array of published pages
     *
     * This test verifies that getPublished returns an array containing
     * only pages with published status.
     */
    public function test_get_published_returns_array_of_published_pages(): void
    {
        $expectedData = [
            ['id' => '1', 'status' => 'published'],
            ['id' => '2', 'status' => 'published'],
        ];

        $collectionMock = Mockery::mock();
        $collectionMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('get')
            ->once()
            ->andReturn($collectionMock);

        $this->modelMock->shouldReceive('published')
            ->once()
            ->andReturn($queryMock);

        $result = $this->repository->getPublished();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test withWidgets eager loads widgets relationship
     *
     * Verifies that withWidgets method uses Eloquent's with() method
     * to eager load the widgets relationship, preventing N+1 queries.
     */
    public function test_with_widgets_eager_loads_widgets_relationship(): void
    {
        $id = '9d4e6f8a-1234-5678-9abc-def012345678';
        $expectedData = [
            'id' => $id,
            'title' => 'Home',
            'widgets' => [
                [
                    'id' => 'widget-1',
                    'widget' => 'hero',
                    'position_x' => 0,
                    'position_y' => 0
                ]
            ]
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($pageMock);

        $this->modelMock->shouldReceive('with')
            ->once()
            ->with('widgets')
            ->andReturn($queryMock);

        $result = $this->repository->withWidgets($id);

        $this->assertEquals($expectedData, $result);
        $this->assertArrayHasKey('widgets', $result);
    }

    /**
     * Test withWidgets returns null when page not found
     *
     * This test verifies that withWidgets returns null when
     * the requested page does not exist.
     */
    public function test_with_widgets_returns_null_when_page_not_found(): void
    {
        $id = 'non-existent-id';

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn(null);

        $this->modelMock->shouldReceive('with')
            ->once()
            ->with('widgets')
            ->andReturn($queryMock);

        $result = $this->repository->withWidgets($id);

        $this->assertNull($result);
    }

    /**
     * Test withMetatags eager loads metatags relationship
     *
     * Verifies that withMetatags method uses Eloquent's with() method
     * to eager load the metatags relationship, preventing N+1 queries.
     */
    public function test_with_metatags_eager_loads_metatags_relationship(): void
    {
        $id = '9d4e6f8a-1234-5678-9abc-def012345678';
        $expectedData = [
            'id' => $id,
            'title' => 'Home',
            'metatags' => [
                [
                    'name' => 'description',
                    'content' => 'Home page description'
                ]
            ]
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($pageMock);

        $this->modelMock->shouldReceive('with')
            ->once()
            ->with('metatags')
            ->andReturn($queryMock);

        $result = $this->repository->withMetatags($id);

        $this->assertEquals($expectedData, $result);
        $this->assertArrayHasKey('metatags', $result);
    }

    /**
     * Test withMetatags returns null when page not found
     *
     * This test verifies that withMetatags returns null when
     * the requested page does not exist.
     */
    public function test_with_metatags_returns_null_when_page_not_found(): void
    {
        $id = 'non-existent-id';

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn(null);

        $this->modelMock->shouldReceive('with')
            ->once()
            ->with('metatags')
            ->andReturn($queryMock);

        $result = $this->repository->withMetatags($id);

        $this->assertNull($result);
    }

    /**
     * Test getByStatus filters by status
     *
     * This test verifies that the getByStatus method correctly filters
     * pages by their status using a where clause.
     */
    public function test_get_by_status_filters_by_status(): void
    {
        $status = 'draft';
        $expectedData = [
            ['id' => '1', 'status' => 'draft', 'title' => 'Draft 1'],
            ['id' => '2', 'status' => 'draft', 'title' => 'Draft 2'],
        ];

        $collectionMock = Mockery::mock();
        $collectionMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('get')
            ->once()
            ->andReturn($collectionMock);

        $this->modelMock->shouldReceive('where')
            ->once()
            ->with('status', $status)
            ->andReturn($queryMock);

        $result = $this->repository->getByStatus($status);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test getByStatus returns empty array when no matches
     *
     * This test verifies that getByStatus returns an empty array
     * when no pages match the requested status.
     */
    public function test_get_by_status_returns_empty_array_when_no_matches(): void
    {
        $status = 'archived';

        $collectionMock = Mockery::mock();
        $collectionMock->shouldReceive('toArray')
            ->once()
            ->andReturn([]);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('get')
            ->once()
            ->andReturn($collectionMock);

        $this->modelMock->shouldReceive('where')
            ->once()
            ->with('status', $status)
            ->andReturn($queryMock);

        $result = $this->repository->getByStatus($status);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
