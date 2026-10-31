<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Collapsible sections; with multiple, works as a toggle.
 */
class Accordion extends WidgetDefinition
{
    public string $name = 'accordion';

    public ?string $label = 'Accordion';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('items', fn (Schema $i) => $i->string('title')->required()->maxLength(200)->richText('content'))->required()->min(1)->label('Items')
            ->boolean('multiple')->default(false)->label('Allow several open (toggle)')
            ->boolean('first_open')->default(true)->label('Open the first item');
    }

    public function example(): array
    {
        return ['items' => [['title' => 'What is it?', 'content' => '<p>A page builder.</p>'], ['title' => 'Is it free?', 'content' => '<p>Yes.</p>']]];
    }
}
