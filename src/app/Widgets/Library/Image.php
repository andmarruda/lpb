<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A single image with optional caption and link.
 */
class Image extends WidgetDefinition
{
    public string $name = 'image';

    public ?string $label = 'Image';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->media('image')->required()->label('Image')
            ->string('alt')->maxLength(300)->label('Alternative text')
            ->string('caption')->maxLength(500)->label('Caption')
            ->link('link')->label('Link');
    }

    public function example(): array
    {
        return ['image' => ['$media' => 1], 'alt' => 'A mountain at sunrise', 'caption' => 'Sunrise'];
    }
}
