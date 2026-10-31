<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * An invisible target for #links.
 */
class Anchor extends WidgetDefinition
{
    public string $name = 'anchor';

    public ?string $label = 'Menu anchor';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('id')->required()->pattern('^[A-Za-z][A-Za-z0-9_-]*$')->maxLength(100)->label('Anchor id');
    }

    public function example(): array
    {
        return ['id' => 'contact'];
    }
}
