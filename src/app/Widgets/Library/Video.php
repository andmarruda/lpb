<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * An embedded YouTube or Vimeo video, or a video file.
 */
class Video extends WidgetDefinition
{
    public string $name = 'video';

    public ?string $label = 'Video';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->enum('source', ['youtube', 'vimeo', 'file'])->required()->default('youtube')->label('Source')
            ->url('url')->label('URL')->help('YouTube or Vimeo link')
            ->media('file')->label('Video file')
            ->media('poster')->label('Poster image')
            ->enum('aspect_ratio', ['16:9', '4:3', '1:1', '9:16', '21:9'])->default('16:9')->label('Aspect ratio')
            ->boolean('autoplay')->default(false)->label('Autoplay')
            ->boolean('muted')->default(false)->label('Muted')
            ->boolean('loop')->default(false)->label('Loop')
            ->boolean('controls')->default(true)->label('Controls');
    }

    public function example(): array
    {
        return ['source' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'aspect_ratio' => '16:9'];
    }
}
