<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Repositories\Sql\PageRepository;
use Andmarruda\Lpb\Tests\Feature\Concerns\AcceptanceScenario;

/**
 * Acceptance scenario on the SQL driver
 */
class AcceptanceTest extends TestCase
{
    use RefreshDatabase, AcceptanceScenario, RegistersTestWidgets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerTestWidgets();
    }

    /**
     * Test the scenario runs against the SQL repositories
     */
    public function test_runs_on_sql_driver(): void
    {
        $this->assertInstanceOf(PageRepository::class, app(PageRepositoryInterface::class));
    }
}
