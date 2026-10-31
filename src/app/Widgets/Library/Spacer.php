<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Empty vertical space.
 */
class Spacer extends WidgetDefinition
{
    public string $name = 'spacer';

    public ?string $label = 'Spacer';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->integer('height')->required()->min(0)->max(1000)->default(40)->label('Height (px)');
    }

    public function example(): array
    {
        return ['height' => 40];
    }
}
