<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Tests\TestCase;
use LogicException;
use Andmarruda\Lpb\Contracts\RevisionRepositoryInterface;
use Andmarruda\Lpb\Exceptions\InvalidRelationException;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Repositories\Mongo\RevisionRepository;
use Andmarruda\Lpb\Models\Mongo\Revision;
use Andmarruda\Lpb\ValueObjects\PublishState;

/**
 * MongoDB RevisionRepository integration tests
 */
class RevisionIntegrationTest extends TestCase
{
    protected RevisionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Revision::truncate();

        $this->repository = new RevisionRepository(new Revision());
    }

    protected function tearDown(): void
    {
        Revision::truncate();

        parent::tearDown();
    }

    /**
     * Test the container binds the MongoDB revision repository
     */
    public function test_container_binds_revision_repository(): void
    {
        $this->assertInstanceOf(RevisionRepository::class, app(RevisionRepositoryInterface::class));
    }

    /**
     * Test record stores the payload, state and author
     */
    public function test_record(): void
    {
        $payload = ['title' => 'Home', 'blocks' => [['id' => 'b1', 'widget_instances' => [['id' => 'w1', 'config' => ['a' => 1]]]]]];

        $revision = $this->repository->record('page', 'p1', $payload, PublishState::Published, 'user-7');

        $this->assertSame('page', $revision['revisionable_type']);
        $this->assertSame('p1', $revision['revisionable_id']);
        $this->assertSame($payload, $revision['payload']);
        $this->assertSame('published', $revision['status']);
        $this->assertSame('user-7', $revision['created_by']);
        $this->assertArrayHasKey('created_at', $revision);
        $this->assertArrayNotHasKey('updated_at', $revision);
    }

    /**
     * Test revisions are listed newest first and scoped to their entity
     */
    public function test_get_for_and_latest(): void
    {
        $first = $this->repository->record('page', 'p1', ['v' => 1], PublishState::Published);
        $second = $this->repository->record('page', 'p1', ['v' => 2], PublishState::Draft);
        $this->repository->record('page', 'p2', ['v' => 3], PublishState::Published);
        $this->repository->record('template', 'p1', ['v' => 4], PublishState::Published);

        $this->assertSame([$second['id'], $first['id']], array_column($this->repository->getFor('page', 'p1'), 'id'));
        $this->assertSame($second['id'], $this->repository->getLatestFor('page', 'p1')['id']);
        $this->assertSame([], $this->repository->getFor('page', 'missing'));
        $this->assertNull($this->repository->getLatestFor('page', 'missing'));
    }

    /**
     * Test restore returns the payload of a revision of the same entity only
     */
    public function test_restore(): void
    {
        $revision = $this->repository->record('page', 'p1', ['title' => 'Home'], PublishState::Published);
        $this->repository->record('page', 'p1', ['title' => 'Later'], PublishState::Draft);

        $this->assertSame(['title' => 'Home'], $this->repository->restore('page', 'p1', $revision['id']));

        $this->expectException(InvalidRelationException::class);
        $this->repository->restore('page', 'p2', $revision['id']);
    }

    /**
     * Test an empty type is rejected
     */
    public function test_rejects_empty_type(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->repository->record(' ', 'p1', [], PublishState::Draft);
    }

    /**
     * Test revisions cannot be updated or deleted
     */
    public function test_revisions_are_append_only(): void
    {
        $revision = $this->repository->record('page', 'p1', ['title' => 'Home'], PublishState::Published);
        $model = Revision::find($revision['id']);

        try {
            $model->update(['status' => PublishState::Draft]);
            $this->fail('Revision was updated');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $model->delete();
    }
}
