<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;
use Andmarruda\Lpb\Repositories\Sql\PageRepository;
use Andmarruda\Lpb\Tests\Feature\Concerns\AcceptanceScenario;

/**
 * Phase 1 acceptance scenario on the SQL driver
 */
class Phase1AcceptanceTest extends TestCase
{
    use RefreshDatabase, AcceptanceScenario;

    /**
     * Test the scenario runs against the SQL repositories
     */
    public function test_runs_on_sql_driver(): void
    {
        $this->assertInstanceOf(PageRepository::class, app(PageRepositoryInterface::class));
    }
}
