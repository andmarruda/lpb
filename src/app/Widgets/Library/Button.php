<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A call-to-action button.
 */
class Button extends WidgetDefinition
{
    public string $name = 'button';

    public ?string $label = 'Button';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('text')->required()->maxLength(120)->label('Text')
            ->link('link')->label('Link')
            ->enum('variant', ['primary', 'secondary', 'outline', 'link'])->default('primary')->label('Style')
            ->enum('size', ['xs', 'sm', 'md', 'lg', 'xl'])->default('md')->label('Size')
            ->string('icon')->label('Icon')
            ->enum('icon_position', ['before', 'after'])->default('before')->label('Icon position')
            ->boolean('full_width')->default(false)->label('Full width');
    }

    public function example(): array
    {
        return ['text' => 'Get started', 'link' => ['url' => '/start'], 'variant' => 'primary', 'size' => 'md'];
    }
}
