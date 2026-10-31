<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A number that counts up when it comes into view.
 */
class Counter extends WidgetDefinition
{
    public string $name = 'counter';

    public ?string $label = 'Counter';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->number('start')->default(0)->label('Start')
            ->number('end')->required()->label('End')
            ->integer('duration')->min(0)->max(60000)->default(2000)->label('Animation (ms)')
            ->string('prefix')->maxLength(20)->label('Prefix')
            ->string('suffix')->maxLength(20)->label('Suffix')
            ->string('title')->maxLength(200)->label('Title')
            ->boolean('thousand_separator')->default(true)->label('Thousand separator');
    }

    public function example(): array
    {
        return ['start' => 0, 'end' => 1500, 'suffix' => '+', 'title' => 'Happy customers'];
    }
}
