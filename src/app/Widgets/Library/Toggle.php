<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Collapsible sections that open independently.
 */
class Toggle extends WidgetDefinition
{
    public string $name = 'toggle';

    public ?string $label = 'Toggle';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('items', fn (Schema $i) => $i->string('title')->required()->maxLength(200)->richText('content'))->required()->min(1)->label('Items')
            ->boolean('first_open')->default(false)->label('Open the first item');
    }

    public function example(): array
    {
        return ['items' => [['title' => 'Shipping', 'content' => '<p>Ships in 2 days.</p>'], ['title' => 'Returns', 'content' => '<p>30 days to return.</p>']]];
    }
}
