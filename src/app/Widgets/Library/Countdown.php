<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A countdown to a date.
 */
class Countdown extends WidgetDefinition
{
    public string $name = 'countdown';

    public ?string $label = 'Countdown';

    public ?string $category = 'marketing';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('due')->required()->pattern('^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})?$')->label('Due date (ISO 8601)')
            ->boolean('days')->default(true)->label('Days')
            ->boolean('hours')->default(true)->label('Hours')
            ->boolean('minutes')->default(true)->label('Minutes')
            ->boolean('seconds')->default(true)->label('Seconds')
            ->string('expired_message')->maxLength(300)->label('Message after the due date');
    }

    public function example(): array
    {
        return ['due' => '2030-01-01T00:00:00Z', 'expired_message' => 'The offer has ended.'];
    }
}
