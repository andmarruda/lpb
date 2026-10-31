<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A horizontal rule, optionally with text.
 */
class Divider extends WidgetDefinition
{
    public string $name = 'divider';

    public ?string $label = 'Divider';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->enum('style', ['solid', 'dashed', 'dotted', 'double'])->default('solid')->label('Style')
            ->integer('weight')->min(1)->max(20)->default(1)->label('Weight (px)')
            ->string('text')->maxLength(120)->label('Text in the middle');
    }

    public function example(): array
    {
        return ['style' => 'solid', 'weight' => 1];
    }
}
