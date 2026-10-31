<?php

namespace Andmarruda\Lpb\Tests\Fixtures\Widgets;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

class Hero extends WidgetDefinition
{
    public string $name = 'hero';

    public ?string $label = 'Hero banner';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('title')->label('Title')->maxLength(120)
            ->string('subtitle')
            ->media('image')
            ->array('buttons', fn (Schema $b) => $b->string('label')->required()->string('url')->required());
    }
}
