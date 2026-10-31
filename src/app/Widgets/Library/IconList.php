<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A list of items with icons.
 */
class IconList extends WidgetDefinition
{
    public string $name = 'icon-list';

    public ?string $label = 'Icon list';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('items', fn (Schema $i) => $i->string('text')->required()->maxLength(300)->string('icon')->maxLength(60)->link('link'))->required()->label('Items')
            ->enum('layout', ['vertical', 'horizontal'])->default('vertical')->label('Layout');
    }

    public function example(): array
    {
        return ['items' => [['text' => 'Free shipping', 'icon' => 'check'], ['text' => '24/7 support', 'icon' => 'check']]];
    }
}
