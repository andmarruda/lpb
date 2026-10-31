<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * An icon with a title and description.
 */
class IconBox extends WidgetDefinition
{
    public string $name = 'icon-box';

    public ?string $label = 'Icon box';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('icon')->required()->maxLength(60)->label('Icon')
            ->string('title')->required()->maxLength(200)->label('Title')
            ->richText('description')->label('Description')
            ->link('link')->label('Link')
            ->enum('position', ['top', 'left', 'right'])->default('top')->label('Icon position');
    }

    public function example(): array
    {
        return ['icon' => 'rocket', 'title' => 'Fast', 'description' => '<p>Pages load in a blink.</p>'];
    }
}
