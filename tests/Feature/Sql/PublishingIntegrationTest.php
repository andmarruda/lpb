<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Contracts\PublishingServiceInterface;
use Andmarruda\Lpb\Publishing\PublishingService;
use Andmarruda\Lpb\Tests\Feature\Concerns\PublishingScenario;
use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;

/**
 * Publishing, retention and rollback on the SQL driver
 */
class PublishingIntegrationTest extends TestCase
{
    use RefreshDatabase, RegistersTestWidgets, PublishingScenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerTestWidgets();
    }

    /**
     * Test the container binds the publishing service
     */
    public function test_container_binds_publishing_service(): void
    {
        $this->assertInstanceOf(PublishingService::class, app(PublishingServiceInterface::class));
    }
}
