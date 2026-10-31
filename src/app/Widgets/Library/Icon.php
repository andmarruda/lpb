<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A single icon.
 */
class Icon extends WidgetDefinition
{
    public string $name = 'icon';

    public ?string $label = 'Icon';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('icon')->required()->maxLength(60)->label('Icon')->help('A name from the icon set, or an emoji')
            ->enum('view', ['default', 'stacked', 'framed'])->default('default')->label('View')
            ->enum('shape', ['circle', 'square'])->default('circle')->label('Shape')
            ->integer('size')->min(8)->max(300)->default(48)->label('Size (px)')
            ->link('link')->label('Link');
    }

    public function example(): array
    {
        return ['icon' => 'star', 'view' => 'stacked', 'size' => 48];
    }
}
