<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A coloured notice.
 */
class Alert extends WidgetDefinition
{
    public string $name = 'alert';

    public ?string $label = 'Alert';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->enum('type', ['info', 'success', 'warning', 'danger'])->default('info')->label('Type')
            ->string('title')->required()->maxLength(200)->label('Title')
            ->richText('description')->label('Description')
            ->boolean('dismissible')->default(false)->label('Dismissible');
    }

    public function example(): array
    {
        return ['type' => 'warning', 'title' => 'Heads up', 'description' => '<p>This is an alert.</p>'];
    }
}
