<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A site menu by location.
 */
class NavMenu extends WidgetDefinition
{
    public string $name = 'nav-menu';

    public ?string $label = 'Nav menu';

    public ?string $category = 'navigation';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('location')->required()->maxLength(120)->label('Menu location')->help('Location of a menu, loaded from the delivery API')
            ->enum('layout', ['horizontal', 'vertical', 'dropdown'])->default('horizontal')->label('Layout')
            ->enum('align', ['left', 'center', 'right', 'justify'])->default('left')->label('Alignment');
    }

    public function example(): array
    {
        return ['location' => 'header', 'layout' => 'horizontal'];
    }
}
