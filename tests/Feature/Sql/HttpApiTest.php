<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Tests\Feature\Concerns\HttpApiScenario;
use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;

/**
 * HTTP API and shell views on the SQL driver
 */
class HttpApiTest extends TestCase
{
    use RefreshDatabase, RegistersTestWidgets, HttpApiScenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerTestWidgets();
    }
}
