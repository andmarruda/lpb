<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Tests\Feature\Concerns\DeliveryScenario;
use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;

/**
 * Delivery payloads, cache keys and webhooks on the SQL driver
 */
class DeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase, RegistersTestWidgets, DeliveryScenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerTestWidgets();
    }
}
