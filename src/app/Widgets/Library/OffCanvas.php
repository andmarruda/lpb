<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A side panel opened by a button.
 */
class OffCanvas extends WidgetDefinition
{
    public string $name = 'off-canvas';

    public ?string $label = 'Off-canvas';

    public ?string $category = 'navigation';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('trigger_text')->required()->maxLength(60)->default('Open')->label('Button text')
            ->string('title')->maxLength(200)->label('Panel title')
            ->richText('content')->label('Content')
            ->enum('position', ['left', 'right'])->default('right')->label('Side');
    }

    public function example(): array
    {
        return ['trigger_text' => 'Menu', 'title' => 'Quick links', 'content' => '<ul><li><a href="/">Home</a></li><li><a href="/about">About</a></li></ul>'];
    }
}
