<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A headline with rotating words.
 */
class AnimatedHeadline extends WidgetDefinition
{
    public string $name = 'animated-headline';

    public ?string $label = 'Animated headline';

    public ?string $category = 'marketing';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('before')->maxLength(200)->label('Before text')
            ->strings('rotating')->required()->min(1)->label('Rotating words')
            ->string('after')->maxLength(200)->label('After text')
            ->enum('animation', ['typing', 'fade', 'slide'])->default('typing')->label('Animation')
            ->enum('tag', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'])->default('h2')->label('HTML tag');
    }

    public function example(): array
    {
        return ['before' => 'We build', 'rotating' => ['websites', 'stores', 'landing pages'], 'animation' => 'typing'];
    }
}
