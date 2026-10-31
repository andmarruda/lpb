<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A pricing plan with features.
 */
class PriceTable extends WidgetDefinition
{
    public string $name = 'price-table';

    public ?string $label = 'Price table';

    public ?string $category = 'marketing';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('heading')->required()->maxLength(120)->label('Heading')
            ->string('subheading')->maxLength(200)->label('Subheading')
            ->string('currency')->maxLength(10)->default('$')->label('Currency symbol')
            ->string('price')->required()->maxLength(20)->label('Price')
            ->string('period')->maxLength(40)->label('Period')
            ->array('features', fn (Schema $i) => $i->string('text')->required()->maxLength(200)->boolean('included')->default(true))->label('Features')
            ->string('button_text')->maxLength(120)->label('Button text')
            ->link('link')->label('Link')
            ->string('ribbon')->maxLength(40)->label('Ribbon');
    }

    public function example(): array
    {
        return ['heading' => 'Pro', 'currency' => 'R$', 'price' => '49', 'period' => '/month', 'features' => [['text' => 'Unlimited pages'], ['text' => 'Priority support', 'included' => false]], 'button_text' => 'Choose', 'ribbon' => 'Popular'];
    }
}
