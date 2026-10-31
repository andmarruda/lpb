<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A grid of images with an optional lightbox.
 */
class Gallery extends WidgetDefinition
{
    public string $name = 'gallery';

    public ?string $label = 'Gallery';

    public ?string $category = 'media';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('images', fn (Schema $i) => $i->media('image')->required()->string('caption')->maxLength(300)->string('alt')->maxLength(300))->required()->label('Images')
            ->integer('columns')->min(1)->max(10)->default(3)->label('Columns')
            ->boolean('lightbox')->default(true)->label('Open in a lightbox');
    }

    public function example(): array
    {
        return ['images' => [['image' => ['$media' => 1]], ['image' => ['$media' => 2]]], 'columns' => 3];
    }
}
