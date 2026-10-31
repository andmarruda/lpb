<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Tests\TestCase;
use Andmarruda\Lpb\Models\Mongo\GlobalSetting;
use Andmarruda\Lpb\Models\Mongo\Media;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Models\Mongo\Revision;
use Andmarruda\Lpb\Models\Mongo\Template;
use Andmarruda\Lpb\Tests\Feature\Concerns\DeliveryScenario;
use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;

/**
 * Delivery payloads, cache keys and webhooks on the MongoDB driver
 */
class DeliveryIntegrationTest extends TestCase
{
    use RegistersTestWidgets, DeliveryScenario;

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
        foreach ([Page::class, Revision::class, Template::class, Media::class, GlobalSetting::class] as $model) {
            $model::truncate();
        }
    }
}
