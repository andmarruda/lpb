<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Raw HTML, rendered without sanitising.
 */
class Html extends WidgetDefinition
{
    public string $name = 'html';

    public ?string $label = 'HTML';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('code')->required()->maxLength(100000)->label('HTML code')->help('Rendered as is: only trusted editors should use it');
    }

    public function example(): array
    {
        return ['code' => '<p>Custom <strong>HTML</strong></p>'];
    }
}
