<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Andmarruda\Lpb\Tests\Feature\Concerns\MediaStorageScenario;
use Andmarruda\Lpb\Tests\Feature\Concerns\RegistersTestWidgets;

/**
 * Media uploads, replace and delete on the SQL driver
 */
class MediaStorageTest extends TestCase
{
    use RefreshDatabase, RegistersTestWidgets, MediaStorageScenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerTestWidgets();
    }
}
