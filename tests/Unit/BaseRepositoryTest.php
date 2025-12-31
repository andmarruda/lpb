<?php

namespace Andmarruda\Lpb\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Mockery;
use Andmarruda\Lpb\Repositories\BaseRepository;
use Andmarruda\Lpb\Models\Sql\Page;

/**
 * Unit tests for BaseRepository
 *
 * This test suite validates the base CRUD operations that all
 * repositories inherit from BaseRepository.
 */
class BaseRepositoryTest extends TestCase
{
    protected $repository;
    protected $modelMock;

    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->modelMock = Mockery::mock(Page::class);
        $this->repository = new class($this->modelMock) extends BaseRepository {};
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
     * Test find method returns array when record exists
     *
     * This test verifies that when a record exists in the repository,
     * the find method returns the record data as an array.
     */
    public function test_find_returns_array_when_record_exists(): void
    {
        $id = '9d4e6f8a-1234-5678-9abc-def012345678';
        $expectedData = [
            'id' => $id,
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published'
        ];

        $recordMock = Mockery::mock();
        $recordMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($recordMock);

        $result = $this->repository->find($id);

        $this->assertEquals($expectedData, $result);
    }

    /**
     * Test find method returns null when record does not exist
     *
     * This test verifies that when a record does not exist,
     * the find method returns null.
     */
    public function test_find_returns_null_when_record_not_found(): void
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
     * Test all method returns array of records
     *
     * This test verifies that the all method returns all records
     * as an array when records exist.
     */
    public function test_all_returns_array_of_records(): void
    {
        $expectedData = [
            ['id' => '1', 'title' => 'Page 1'],
            ['id' => '2', 'title' => 'Page 2'],
        ];

        $collectionMock = Mockery::mock();
        $collectionMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $this->modelMock->shouldReceive('all')
            ->once()
            ->andReturn($collectionMock);

        $result = $this->repository->all();

        $this->assertEquals($expectedData, $result);
        $this->assertCount(2, $result);
    }

    /**
     * Test all method returns empty array when no records exist
     *
     * This test verifies that the all method returns an empty array
     * when there are no records in the repository.
     */
    public function test_all_returns_empty_array_when_no_records(): void
    {
        $collectionMock = Mockery::mock();
        $collectionMock->shouldReceive('toArray')
            ->once()
            ->andReturn([]);

        $this->modelMock->shouldReceive('all')
            ->once()
            ->andReturn($collectionMock);

        $result = $this->repository->all();

        $this->assertEquals([], $result);
        $this->assertIsArray($result);
    }

    /**
     * Test create method passes data to model and returns array
     *
     * This test verifies that the create method correctly passes
     * the provided data to the model's create method.
     */
    public function test_create_passes_data_to_model_and_returns_array(): void
    {
        $data = [
            'title' => 'New Page',
            'slug' => 'new-page',
            'status' => 'draft'
        ];

        $recordMock = Mockery::mock();
        $recordMock->shouldReceive('toArray')
            ->once()
            ->andReturn(array_merge(['id' => 'new-id'], $data));

        $this->modelMock->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($recordMock);

        $result = $this->repository->create($data);

        $this->assertIsArray($result);
        $this->assertEquals($data['title'], $result['title']);
    }

    /**
     * Test create method returns created record with ID
     *
     * This test verifies that the create method returns the created
     * record including the generated ID.
     */
    public function test_create_returns_created_record_with_id(): void
    {
        $data = [
            'title' => 'Test Page',
            'slug' => 'test-page'
        ];

        $expectedData = array_merge(['id' => '9d4e6f8a-1234-5678-9abc-def012345678'], $data);

        $recordMock = Mockery::mock();
        $recordMock->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedData);

        $this->modelMock->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($recordMock);

        $result = $this->repository->create($data);

        $this->assertArrayHasKey('id', $result);
        $this->assertNotEmpty($result['id']);
    }

    /**
     * Test update method finds and updates record
     *
     * This test verifies that the update method correctly finds
     * the record and updates it with the provided data.
     */
    public function test_update_finds_and_updates_record(): void
    {
        $id = 'test-id';
        $data = ['title' => 'Updated Title'];

        $recordMock = Mockery::mock();
        $recordMock->shouldReceive('update')
            ->once()
            ->with($data);

        $recordMock->shouldReceive('fresh')
            ->once()
            ->andReturnSelf();

        $recordMock->shouldReceive('toArray')
            ->once()
            ->andReturn(array_merge(['id' => $id], $data));

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($recordMock);

        $result = $this->repository->update($id, $data);

        $this->assertNotNull($result);
        $this->assertEquals($data['title'], $result['title']);
    }

    /**
     * Test update method returns fresh record after update
     *
     * This test verifies that the update method returns the
     * freshly updated record from the database.
     */
    public function test_update_returns_fresh_record_after_update(): void
    {
        $id = 'test-id';
        $data = ['status' => 'published'];
        $updatedData = array_merge(['id' => $id, 'title' => 'Test'], $data);

        $recordMock = Mockery::mock();
        $recordMock->shouldReceive('update')
            ->once()
            ->with($data);

        $recordMock->shouldReceive('fresh')
            ->once()
            ->andReturnSelf();

        $recordMock->shouldReceive('toArray')
            ->once()
            ->andReturn($updatedData);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($recordMock);

        $result = $this->repository->update($id, $data);

        $this->assertEquals($updatedData, $result);
    }

    /**
     * Test update method returns null when record not found
     *
     * This test verifies that the update method returns null
     * when the record to update does not exist.
     */
    public function test_update_returns_null_when_record_not_found(): void
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
     * Test delete method removes record and returns true
     *
     * This test verifies that the delete method successfully
     * removes the record and returns true.
     */
    public function test_delete_removes_record_and_returns_true(): void
    {
        $id = 'test-id';

        $recordMock = Mockery::mock();
        $recordMock->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($recordMock);

        $result = $this->repository->delete($id);

        $this->assertTrue($result);
    }

    /**
     * Test delete method returns false when record not found
     *
     * This test verifies that the delete method returns false
     * when the record to delete does not exist.
     */
    public function test_delete_returns_false_when_record_not_found(): void
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
     * Test delete method calls model delete method
     *
     * This test verifies that the delete method correctly calls
     * the model's delete method when a record is found.
     */
    public function test_delete_calls_model_delete_method(): void
    {
        $id = 'test-id';

        $recordMock = Mockery::mock();
        $recordMock->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->modelMock->shouldReceive('find')
            ->once()
            ->with($id)
            ->andReturn($recordMock);

        $this->repository->delete($id);

        $this->expectNotToPerformAssertions();
    }
}
