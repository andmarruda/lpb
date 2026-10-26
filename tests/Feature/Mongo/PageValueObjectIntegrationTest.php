<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Tests\TestCase;
use ValueError;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Repositories\Mongo\PageRepository;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\ValueObjects\Slug;

/**
 * MongoDB Page integration with the Slug and PublishState value objects
 */
class PageValueObjectIntegrationTest extends TestCase
{
    protected PageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Page::truncate();

        $this->repository = new PageRepository(new Page());
    }

    protected function tearDown(): void
    {
        Page::truncate();

        parent::tearDown();
    }

    /**
     * Test status is cast to PublishState on the model and serialised as a string
     */
    public function test_status_is_cast_to_publish_state(): void
    {
        $result = $this->repository->create(['title' => 'Home', 'slug' => 'home', 'status' => 'draft']);

        $this->assertSame('draft', $result['status']);
        $this->assertSame(PublishState::Draft, Page::find($result['id'])->status);
    }

    /**
     * Test status accepts the enum on write
     */
    public function test_status_accepts_enum(): void
    {
        $result = $this->repository->create(['title' => 'Home', 'slug' => 'home', 'status' => PublishState::Published]);

        $this->assertSame('published', $result['status']);
        $this->assertCount(1, $this->repository->getByStatus(PublishState::Published));
        $this->assertCount(1, $this->repository->getByStatus('published'));
    }

    /**
     * Test an unknown status is rejected
     */
    public function test_rejects_unknown_status(): void
    {
        $this->expectException(ValueError::class);

        $this->repository->create(['title' => 'Home', 'slug' => 'home', 'status' => 'deleted']);
    }

    /**
     * Test slug accepts the Slug value object
     */
    public function test_slug_accepts_value_object(): void
    {
        $result = $this->repository->create(['title' => 'About', 'slug' => Slug::fromTitle('About Us'), 'status' => 'draft']);

        $this->assertSame('about-us', $result['slug']);
        $this->assertNotNull($this->repository->findBySlug('about-us'));
    }

    /**
     * Test an invalid slug is rejected on create and update
     */
    public function test_rejects_invalid_slug(): void
    {
        $page = $this->repository->create(['title' => 'Home', 'slug' => 'home', 'status' => 'draft']);

        try {
            $this->repository->update($page['id'], ['slug' => 'Home Page']);
            $this->fail('Invalid slug was accepted on update');
        } catch (InvalidValueException) {
            $this->assertNotNull($this->repository->findBySlug('home'));
        }

        $this->expectException(InvalidValueException::class);
        $this->repository->create(['title' => 'Bad', 'slug' => 'Bad Slug', 'status' => 'draft']);
    }
}
