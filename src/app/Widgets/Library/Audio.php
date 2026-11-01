<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * An audio player.
 */
class Audio extends WidgetDefinition
{
    public string $name = 'audio';

    public ?string $label = 'Audio';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->media('file')->label('Audio file')
            ->url('url')->label('Audio URL')
            ->string('title')->maxLength(200)->label('Title')
            ->boolean('autoplay')->default(false)->label('Autoplay')
            ->boolean('loop')->default(false)->label('Loop');
    }

    public function example(): array
    {
        return ['url' => 'https://example.com/podcast.mp3', 'title' => 'Episode 1'];
    }
}
