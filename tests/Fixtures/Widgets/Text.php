<?php

namespace Andmarruda\Lpb\Tests\Fixtures\Widgets;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

class Text extends WidgetDefinition
{
    public string $name = 'text';

    public function schema(Schema $s): Schema
    {
        return $s->richText('body');
    }
}
