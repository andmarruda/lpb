<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Shows how much of the page has been read.
 */
class ProgressTracker extends WidgetDefinition
{
    public string $name = 'progress-tracker';

    public ?string $label = 'Progress tracker';

    public ?string $category = 'navigation';

    public function schema(Schema $s): Schema
    {
        return $s
            ->enum('type', ['horizontal', 'circular'])->default('horizontal')->label('Type')
            ->enum('position', ['top', 'bottom', 'inline'])->default('top')->label('Position')
            ->boolean('show_percent')->default(false)->label('Show percentage');
    }

    public function example(): array
    {
        return ['type' => 'horizontal', 'position' => 'top'];
    }
}
