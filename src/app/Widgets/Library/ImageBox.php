<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * An image with a title and description.
 */
class ImageBox extends WidgetDefinition
{
    public string $name = 'image-box';

    public ?string $label = 'Image box';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->media('image')->required()->label('Image')
            ->string('title')->required()->maxLength(200)->label('Title')
            ->richText('description')->label('Description')
            ->link('link')->label('Link')
            ->enum('position', ['top', 'left', 'right'])->default('top')->label('Image position');
    }

    public function example(): array
    {
        return ['image' => ['$media' => 1], 'title' => 'Our team', 'description' => '<p>Meet the people behind it.</p>'];
    }
}
