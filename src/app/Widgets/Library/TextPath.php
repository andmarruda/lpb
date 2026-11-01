<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Text drawn along a curved path.
 */
class TextPath extends WidgetDefinition
{
    public string $name = 'text-path';

    public ?string $label = 'Text path';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('text')->required()->maxLength(200)->label('Text')
            ->enum('shape', ['wave', 'arc', 'circle', 'line'])->default('wave')->label('Path')
            ->integer('font_size')->min(8)->max(200)->default(24)->label('Font size (px)')
            ->link('link')->label('Link');
    }

    public function example(): array
    {
        return ['text' => 'Text that follows a curve', 'shape' => 'wave'];
    }
}
