<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * An embedded map of an address.
 */
class Map extends WidgetDefinition
{
    public string $name = 'map';

    public ?string $label = 'Google Maps';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('address')->required()->maxLength(500)->label('Address or coordinates')
            ->integer('zoom')->min(1)->max(20)->default(14)->label('Zoom')
            ->integer('height')->min(100)->max(1500)->default(300)->label('Height (px)');
    }

    public function example(): array
    {
        return ['address' => 'Avenida Paulista, 1000, São Paulo', 'zoom' => 15, 'height' => 300];
    }
}
