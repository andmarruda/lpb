<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A labelled progress bar.
 */
class ProgressBar extends WidgetDefinition
{
    public string $name = 'progress-bar';

    public ?string $label = 'Progress bar';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('title')->maxLength(200)->label('Title')
            ->integer('percent')->required()->min(0)->max(100)->label('Percentage')
            ->string('inner_text')->maxLength(120)->label('Inner text')
            ->enum('type', ['info', 'success', 'warning', 'danger'])->default('info')->label('Type')
            ->boolean('show_percent')->default(true)->label('Show percentage');
    }

    public function example(): array
    {
        return ['title' => 'Design', 'percent' => 80];
    }
}
