<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Tests\TestCase;
use Andmarruda\Lpb\Models\Mongo\GlobalSetting;
use Andmarruda\Lpb\Models\Mongo\Media;
use Andmarruda\Lpb\Models\Mongo\Menu;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Models\Mongo\Revision;
use Andmarruda\Lpb\Models\Mongo\Template;
use Andmarruda\Lpb\Tests\Feature\Concerns\HttpApiScenario;
use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;

/**
 * HTTP API and shell views on the MongoDB driver
 */
class HttpApiTest extends TestCase
{
    use RegistersTestWidgets, HttpApiScenario;

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
        foreach ([Page::class, Revision::class, Template::class, Media::class, Menu::class, GlobalSetting::class] as $model) {
            $model::truncate();
        }
    }
}
