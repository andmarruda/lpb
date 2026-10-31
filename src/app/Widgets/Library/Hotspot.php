<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * An image with points that show details.
 */
class Hotspot extends WidgetDefinition
{
    public string $name = 'hotspot';

    public ?string $label = 'Hotspot';

    public ?string $category = 'media';

    public function schema(Schema $s): Schema
    {
        return $s
            ->media('image')->required()->label('Image')
            ->array('spots', fn (Schema $i) => $i->number('x')->required()->min(0)->max(100)->number('y')->required()->min(0)->max(100)->string('label')->maxLength(120)->string('content')->maxLength(1000))->required()->label('Hotspots');
    }

    public function example(): array
    {
        return ['image' => ['$media' => 1], 'spots' => [['x' => 30, 'y' => 40, 'label' => 'Lens', 'content' => '50mm f/1.8']]];
    }
}
