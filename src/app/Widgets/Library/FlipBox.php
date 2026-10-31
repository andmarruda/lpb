<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A box that flips on hover to show its back.
 */
class FlipBox extends WidgetDefinition
{
    public string $name = 'flip-box';

    public ?string $label = 'Flip box';

    public ?string $category = 'marketing';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('front_icon')->maxLength(60)->label('Front icon')
            ->media('front_image')->label('Front background')
            ->string('front_title')->required()->maxLength(200)->label('Front title')
            ->string('front_description')->maxLength(1000)->label('Front description')
            ->string('back_title')->maxLength(200)->label('Back title')
            ->string('back_description')->maxLength(1000)->label('Back description')
            ->string('button_text')->maxLength(120)->label('Button text')
            ->link('link')->label('Link')
            ->enum('effect', ['flip', 'slide', 'fade'])->default('flip')->label('Effect')
            ->enum('direction', ['horizontal', 'vertical'])->default('horizontal')->label('Direction');
    }

    public function example(): array
    {
        return ['front_icon' => 'star', 'front_title' => 'Hover me', 'back_title' => 'Surprise', 'back_description' => 'More details on the back.'];
    }
}
