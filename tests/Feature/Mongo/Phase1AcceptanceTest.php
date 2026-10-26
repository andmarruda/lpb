<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Tests\TestCase;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Models\Mongo\Revision;
use Andmarruda\Lpb\Models\Mongo\Template;
use Andmarruda\Lpb\Repositories\Mongo\PageRepository;
use Andmarruda\Lpb\Tests\Feature\Concerns\AcceptanceScenario;

/**
 * Phase 1 acceptance scenario on the MongoDB driver
 */
class Phase1AcceptanceTest extends TestCase
{
    use AcceptanceScenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate();
    }

    protected function tearDown(): void
    {
        $this->truncate();

        parent::tearDown();
    }

    protected function truncate(): void
    {
        Page::truncate();
        Template::truncate();
        Revision::truncate();
    }

    /**
     * Test the scenario runs against the MongoDB repositories
     */
    public function test_runs_on_mongodb_driver(): void
    {
        $this->assertInstanceOf(PageRepository::class, app(PageRepositoryInterface::class));
    }
}
