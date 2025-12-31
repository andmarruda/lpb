<?php

namespace Andmarruda\Lpb\Tests\Unit\Repositories\Mongo;

use PHPUnit\Framework\TestCase;
use Mockery;
use Andmarruda\Lpb\Repositories\Mongo\PageRepository;
use Andmarruda\Lpb\Models\Mongo\Page;

/**
 * Unit tests for MongoDB PageRepository
 *
 * This test suite validates the behavior of PageRepository for MongoDB
 * databases using Mockery for mocking dependencies. MongoDB implementation
 * uses embedded documents for widgets and metatags.
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
     * when the page exists in MongoDB.
     */
    public function test_find_returns_array_when_page_exists(): void
    {
        $id = 'mongo-id-123';
        $expectedData = [
            '_id' => $id,
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
     * the requested page does not exist in MongoDB.
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
     * data to the MongoDB model and returns the created page.
     */
    public function test_create_creates_new_page(): void
    {
        $data = [
            'title' => 'New Page',
            'slug' => 'new-page',
            'status' => 'draft',
            'widgets' => [],
            'metatags' => []
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn(array_merge(['_id' => 'new-mongo-id'], $data));

        $this->modelMock->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($pageMock);

        $result = $this->repository->create($data);

        $this->assertArrayHasKey('_id', $result);
        $this->assertEquals($data['title'], $result['title']);
    }

    /**
     * Test update method updates existing page
     *
     * This test verifies that the update method finds and updates
     * the page with the provided data in MongoDB.
     */
    public function test_update_updates_existing_page(): void
    {
        $id = 'mongo-id-123';
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
            ->andReturn(array_merge(['_id' => $id], $data));

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($pageMock);

        $result = $this->repository->update($id, $data);

        $this->assertNotNull($result);
        $this->assertEquals($data['title'], $result['title']);
    }

    /**
     * Test delete method deletes page
     *
     * This test verifies that the delete method successfully
     * removes the page from MongoDB and returns true.
     */
    public function test_delete_deletes_page(): void
    {
        $id = 'mongo-id-123';

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
     * Test findBySlug uses bySlug scope
     *
     * This test verifies that the findBySlug method uses the bySlug
     * scope to find pages by their slug in MongoDB.
     */
    public function test_find_by_slug_uses_by_slug_scope(): void
    {
        $slug = 'test-page';
        $expectedData = [
            '_id' => 'mongo-id-123',
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
     * Test findBySlug returns null when not found
     *
     * This test verifies that findBySlug returns null when no page
     * with the given slug exists in MongoDB.
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
     * scope to retrieve only published pages from MongoDB.
     */
    public function test_get_published_uses_published_scope(): void
    {
        $expectedData = [
            ['_id' => '1', 'status' => 'published', 'title' => 'Page 1'],
            ['_id' => '2', 'status' => 'published', 'title' => 'Page 2'],
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
     * Test getByStatus filters by status
     *
     * This test verifies that the getByStatus method correctly filters
     * pages by their status in MongoDB.
     */
    public function test_get_by_status_filters_by_status(): void
    {
        $status = 'draft';
        $expectedData = [
            ['_id' => '1', 'status' => 'draft', 'title' => 'Draft 1'],
            ['_id' => '2', 'status' => 'draft', 'title' => 'Draft 2'],
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
     * Test withWidgets returns embedded data without eager loading
     *
     * In MongoDB, widgets are already embedded in the page document,
     * so no eager loading is performed. This test verifies that
     * find() is called directly without with().
     */
    public function test_with_widgets_returns_embedded_data_without_eager_loading(): void
    {
        $id = 'mongo-id-123';
        $expectedData = [
            '_id' => $id,
            'title' => 'Home',
            'widgets' => [
                [
                    'widget' => 'hero',
                    'position_x' => 0,
                    'position_y' => 0,
                    'settings' => [
                        ['key' => 'title', 'value' => 'Welcome'],
                        ['key' => 'image', 'value' => 'hero.jpg']
                    ]
                ]
            ]
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($pageMock);

        $result = $this->repository->withWidgets($id);

        $this->assertEquals($expectedData, $result);
        $this->assertArrayHasKey('widgets', $result);
    }

    /**
     * Test withWidgets includes widgets array in response
     *
     * This test verifies that the withWidgets method returns page data
     * with the embedded widgets array included.
     */
    public function test_with_widgets_includes_widgets_array_in_response(): void
    {
        $id = 'mongo-id-123';
        $widget = ['widget' => 'text_block', 'position_x' => 1];
        $expectedData = [
            '_id' => $id,
            'widgets' => [$widget]
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($pageMock);

        $result = $this->repository->withWidgets($id);

        $this->assertArrayHasKey('widgets', $result);
        $this->assertIsArray($result['widgets']);
    }

    /**
     * Test withMetatags returns embedded data without eager loading
     *
     * In MongoDB, metatags are embedded in the page document,
     * so no eager loading is needed. This test verifies direct find() call.
     */
    public function test_with_metatags_returns_embedded_data_without_eager_loading(): void
    {
        $id = 'mongo-id-123';
        $expectedData = [
            '_id' => $id,
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

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($pageMock);

        $result = $this->repository->withMetatags($id);

        $this->assertEquals($expectedData, $result);
        $this->assertArrayHasKey('metatags', $result);
    }

    /**
     * Test withMetatags includes metatags array in response
     *
     * This test verifies that the withMetatags method returns page data
     * with the embedded metatags array included.
     */
    public function test_with_metatags_includes_metatags_array_in_response(): void
    {
        $id = 'mongo-id-123';
        $metatag = ['name' => 'keywords', 'content' => 'laravel, mongodb'];
        $expectedData = [
            '_id' => $id,
            'metatags' => [$metatag]
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($pageMock);

        $result = $this->repository->withMetatags($id);

        $this->assertArrayHasKey('metatags', $result);
        $this->assertIsArray($result['metatags']);
    }

    /**
     * Test addWidget adds widget to page
     *
     * Verifies that addWidget method calls the model's addWidget method
     * and returns the updated page data.
     */
    public function test_add_widget_adds_widget_to_page(): void
    {
        $pageId = 'mongo-id-123';
        $widget = [
            'widget' => 'text_block',
            'position_x' => 1,
            'position_y' => 0,
            'settings' => [
                ['key' => 'content', 'value' => 'Hello World']
            ]
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('addWidget')
            ->once()
            ->with($widget);

        $pageMock->shouldReceive('fresh')
            ->once()
            ->andReturnSelf();

        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn([
                '_id' => $pageId,
                'widgets' => [$widget]
            ]);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn($pageMock);

        $result = $this->repository->addWidget($pageId, $widget);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('widgets', $result);
    }

    /**
     * Test addWidget calls model addWidget method
     *
     * This test verifies that the addWidget repository method
     * correctly delegates to the model's addWidget method.
     */
    public function test_add_widget_calls_model_add_widget_method(): void
    {
        $pageId = 'mongo-id-123';
        $widget = ['widget' => 'hero', 'position_x' => 0];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('addWidget')
            ->once()
            ->with($widget);

        $pageMock->shouldReceive('fresh')
            ->once()
            ->andReturnSelf();

        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn(['_id' => $pageId, 'widgets' => [$widget]]);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn($pageMock);

        $this->repository->addWidget($pageId, $widget);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test addWidget returns updated page
     *
     * This test verifies that addWidget returns the freshly updated
     * page data after adding the widget.
     */
    public function test_add_widget_returns_updated_page(): void
    {
        $pageId = 'mongo-id-123';
        $widget = ['widget' => 'gallery'];
        $expectedData = [
            '_id' => $pageId,
            'title' => 'Test',
            'widgets' => [$widget]
        ];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('addWidget')
            ->once()
            ->with($widget);

        $pageMock->shouldReceive('fresh')
            ->once()
            ->andReturnSelf();

        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn($pageMock);

        $result = $this->repository->addWidget($pageId, $widget);

        $this->assertEquals($expectedData, $result);
        $this->assertCount(1, $result['widgets']);
    }

    /**
     * Test addWidget returns null when page not found
     *
     * This test verifies that addWidget returns null when trying
     * to add a widget to a non-existent page.
     */
    public function test_add_widget_returns_null_when_page_not_found(): void
    {
        $pageId = 'non-existent-id';
        $widget = ['widget' => 'hero'];

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn(null);

        $result = $this->repository->addWidget($pageId, $widget);

        $this->assertNull($result);
    }

    /**
     * Test updateWidget updates widget at index
     *
     * This test verifies that updateWidget calls the model's updateWidget
     * method with the correct index and data.
     */
    public function test_update_widget_updates_widget_at_index(): void
    {
        $pageId = 'mongo-id-123';
        $widgetIndex = 0;
        $widgetData = ['position_x' => 2, 'position_y' => 1];

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('updateWidget')
            ->once()
            ->with($widgetIndex, $widgetData);

        $pageMock->shouldReceive('fresh')
            ->once()
            ->andReturnSelf();

        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn(['_id' => $pageId]);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn($pageMock);

        $result = $this->repository->updateWidget($pageId, $widgetIndex, $widgetData);

        $this->assertNotNull($result);
    }

    /**
     * Test updateWidget returns null when page not found
     *
     * This test verifies that updateWidget returns null when trying
     * to update a widget on a non-existent page.
     */
    public function test_update_widget_returns_null_when_page_not_found(): void
    {
        $pageId = 'non-existent-id';
        $widgetIndex = 0;
        $widgetData = ['position_x' => 1];

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn(null);

        $result = $this->repository->updateWidget($pageId, $widgetIndex, $widgetData);

        $this->assertNull($result);
    }

    /**
     * Test removeWidget removes widget at index
     *
     * This test verifies that removeWidget calls the model's removeWidget
     * method with the correct index.
     */
    public function test_remove_widget_removes_widget_at_index(): void
    {
        $pageId = 'mongo-id-123';
        $widgetIndex = 1;

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('removeWidget')
            ->once()
            ->with($widgetIndex);

        $pageMock->shouldReceive('fresh')
            ->once()
            ->andReturnSelf();

        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn([
                '_id' => $pageId,
                'widgets' => []
            ]);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn($pageMock);

        $result = $this->repository->removeWidget($pageId, $widgetIndex);

        $this->assertNotNull($result);
    }

    /**
     * Test removeWidget returns null when page not found
     *
     * This test verifies that removeWidget returns null when trying
     * to remove a widget from a non-existent page.
     */
    public function test_remove_widget_returns_null_when_page_not_found(): void
    {
        $pageId = 'non-existent-id';
        $widgetIndex = 0;

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn(null);

        $result = $this->repository->removeWidget($pageId, $widgetIndex);

        $this->assertNull($result);
    }

    /**
     * Test setMetatag sets metatag on page
     *
     * This test verifies that setMetatag calls the model's setMetatag
     * method and returns the updated page.
     */
    public function test_set_metatag_sets_metatag_on_page(): void
    {
        $pageId = 'mongo-id-123';
        $name = 'description';
        $content = 'Test description';

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('setMetatag')
            ->once()
            ->with($name, $content);

        $pageMock->shouldReceive('fresh')
            ->once()
            ->andReturnSelf();

        $pageMock->shouldReceive('toArray')
            ->once()
            ->andReturn([
                '_id' => $pageId,
                'metatags' => [
                    ['name' => $name, 'content' => $content]
                ]
            ]);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn($pageMock);

        $result = $this->repository->setMetatag($pageId, $name, $content);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('metatags', $result);
    }

    /**
     * Test setMetatag returns null when page not found
     *
     * This test verifies that setMetatag returns null when trying
     * to set a metatag on a non-existent page.
     */
    public function test_set_metatag_returns_null_when_page_not_found(): void
    {
        $pageId = 'non-existent-id';
        $name = 'keywords';
        $content = 'test, keywords';

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn(null);

        $result = $this->repository->setMetatag($pageId, $name, $content);

        $this->assertNull($result);
    }

    /**
     * Test getMetatag retrieves metatag value
     *
     * This test verifies that getMetatag calls the model's getMetatag
     * method and returns the metatag value.
     */
    public function test_get_metatag_retrieves_metatag_value(): void
    {
        $pageId = 'mongo-id-123';
        $name = 'keywords';
        $expectedValue = 'laravel, page builder, mongodb';

        $pageMock = Mockery::mock();
        $pageMock->shouldReceive('getMetatag')
            ->once()
            ->with($name)
            ->andReturn($expectedValue);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn($pageMock);

        $result = $this->repository->getMetatag($pageId, $name);

        $this->assertEquals($expectedValue, $result);
    }

    /**
     * Test getMetatag returns null when page not found
     *
     * This test verifies that getMetatag returns null when trying
     * to retrieve a metatag from a non-existent page.
     */
    public function test_get_metatag_returns_null_when_page_not_found(): void
    {
        $pageId = 'non-existent-id';
        $name = 'description';

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($pageId)
            ->andReturn(null);

        $result = $this->repository->getMetatag($pageId, $name);

        $this->assertNull($result);
    }
}
