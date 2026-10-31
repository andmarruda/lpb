<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Images in a sliding carousel.
 */
class ImageCarousel extends WidgetDefinition
{
    public string $name = 'image-carousel';

    public ?string $label = 'Image carousel';

    public ?string $category = 'media';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('slides', fn (Schema $i) => $i->media('image')->required()->string('caption')->maxLength(300)->string('alt')->maxLength(300)->link('link'))->required()->label('Slides')
            ->integer('per_view')->min(1)->max(6)->default(1)->label('Slides per view')
            ->boolean('autoplay')->default(false)->label('Autoplay')
            ->integer('interval')->min(1000)->max(60000)->default(5000)->label('Interval (ms)')
            ->boolean('arrows')->default(true)->label('Arrows')
            ->boolean('dots')->default(true)->label('Dots');
    }

    public function example(): array
    {
        return ['slides' => [['image' => ['$media' => 1]], ['image' => ['$media' => 2]]], 'autoplay' => true];
    }
}
