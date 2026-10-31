<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Full-width slides with headings and buttons.
 */
class Slides extends WidgetDefinition
{
    public string $name = 'slides';

    public ?string $label = 'Slides';

    public ?string $category = 'media';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('slides', fn (Schema $i) => $i->string('heading')->maxLength(200)->string('description')->maxLength(1000)->string('button_text')->maxLength(120)->link('link')->media('background')->string('background_color')->maxLength(40))->required()->label('Slides')
            ->integer('height')->min(100)->max(2000)->default(400)->label('Height (px)')
            ->boolean('autoplay')->default(true)->label('Autoplay')
            ->integer('interval')->min(1000)->max(60000)->default(5000)->label('Interval (ms)')
            ->boolean('arrows')->default(true)->label('Arrows')
            ->boolean('dots')->default(true)->label('Dots');
    }

    public function example(): array
    {
        return ['slides' => [['heading' => 'Welcome', 'description' => 'First slide', 'background_color' => '#1e293b'], ['heading' => 'Discover', 'button_text' => 'Learn more', 'link' => ['url' => '/about']]]];
    }
}
