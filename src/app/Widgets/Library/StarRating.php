<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A rating shown as stars.
 */
class StarRating extends WidgetDefinition
{
    public string $name = 'star-rating';

    public ?string $label = 'Star rating';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->number('rating')->required()->min(0)->max(10)->label('Rating')
            ->enum('scale', [5, 10])->default(5)->label('Scale')
            ->string('title')->maxLength(200)->label('Title');
    }

    public function example(): array
    {
        return ['rating' => 4.5, 'scale' => 5, 'title' => 'Customer rating'];
    }
}
