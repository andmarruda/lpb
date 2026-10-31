<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Tests\TestCase;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Models\Mongo\Revision;
use Andmarruda\Lpb\Tests\Feature\Concerns\PublishingScenario;
use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;

/**
 * Publishing, retention and rollback on the MongoDB driver
 */
class PublishingIntegrationTest extends TestCase
{
    use RegistersTestWidgets, PublishingScenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncate();
        $this->registerTestWidgets();
    }

    protected function tearDown(): void
    {
        $this->truncate();

        parent::tearDown();
    }

    protected function truncate(): void
    {
        Page::truncate();
        Revision::truncate();
    }
}
