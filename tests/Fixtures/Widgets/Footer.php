<?php

namespace Andmarruda\Lpb\Tests\Fixtures\Widgets;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

class Footer extends WidgetDefinition
{
    public string $name = 'footer';

    public function schema(Schema $s): Schema
    {
        return $s;
    }
}
