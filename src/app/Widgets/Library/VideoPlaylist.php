<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Several videos with a list to switch between them.
 */
class VideoPlaylist extends WidgetDefinition
{
    public string $name = 'video-playlist';

    public ?string $label = 'Video playlist';

    public ?string $category = 'media';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('title')->maxLength(200)->label('Title')
            ->array('videos', fn (Schema $i) => $i->string('title')->required()->maxLength(200)->enum('source', ['youtube', 'vimeo', 'file'])->required()->string('url')->maxLength(2048)->media('file')->media('thumbnail')->string('duration')->maxLength(20))->required()->min(1)->label('Videos');
    }

    public function example(): array
    {
        return ['title' => 'Getting started', 'videos' => [['title' => 'Introduction', 'source' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'duration' => '3:32'], ['title' => 'Second part', 'source' => 'vimeo', 'url' => 'https://vimeo.com/76979871']]];
    }
}
