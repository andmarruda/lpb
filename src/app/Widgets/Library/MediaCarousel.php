<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A carousel of images and videos.
 */
class MediaCarousel extends WidgetDefinition
{
    public string $name = 'media-carousel';

    public ?string $label = 'Media carousel';

    public ?string $category = 'media';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('slides', fn (Schema $i) => $i->enum('type', ['image', 'video'])->default('image')->media('image')->url('video_url')->string('caption')->maxLength(300))->required()->label('Slides')
            ->integer('per_view')->min(1)->max(6)->default(3)->label('Slides per view')
            ->boolean('autoplay')->default(false)->label('Autoplay')
            ->integer('interval')->min(1000)->max(60000)->default(5000)->label('Interval (ms)')
            ->boolean('arrows')->default(true)->label('Arrows')
            ->boolean('dots')->default(true)->label('Dots');
    }

    public function example(): array
    {
        return ['slides' => [['type' => 'image', 'image' => ['$media' => 1], 'caption' => 'Photo'], ['type' => 'video', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'], ['type' => 'image', 'image' => ['$media' => 2]]], 'per_view' => 3];
    }
}
