<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Tests\TestCase;
use Andmarruda\Lpb\Models\Mongo\GlobalSetting;
use Andmarruda\Lpb\Models\Mongo\Media;
use Andmarruda\Lpb\Models\Mongo\MediaFile;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Models\Mongo\Revision;
use Andmarruda\Lpb\Tests\Feature\Concerns\MediaStorageScenario;
use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;

/**
 * Media uploads, replace and delete on the MongoDB driver
 */
class MediaStorageTest extends TestCase
{
    use RegistersTestWidgets, MediaStorageScenario;

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
        foreach ([Page::class, Revision::class, Media::class, MediaFile::class, GlobalSetting::class] as $model) {
            $model::truncate();
        }
    }
}
