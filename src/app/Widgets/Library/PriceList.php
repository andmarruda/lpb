<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A menu-style list of items and prices.
 */
class PriceList extends WidgetDefinition
{
    public string $name = 'price-list';

    public ?string $label = 'Price list';

    public ?string $category = 'marketing';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('items', fn (Schema $i) => $i->string('title')->required()->maxLength(200)->string('price')->required()->maxLength(40)->string('description')->maxLength(500)->media('image')->link('link'))->required()->label('Items');
    }

    public function example(): array
    {
        return ['items' => [['title' => 'Espresso', 'price' => 'R$ 6'], ['title' => 'Cappuccino', 'price' => 'R$ 9', 'description' => 'With steamed milk']]];
    }
}
