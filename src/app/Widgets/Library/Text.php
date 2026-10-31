<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Rich text.
 */
class Text extends WidgetDefinition
{
    public string $name = 'text';

    public ?string $label = 'Text editor';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->richText('content')->required()->label('Content');
    }

    public function example(): array
    {
        return ['content' => '<p>Write your text here.</p>'];
    }
}
