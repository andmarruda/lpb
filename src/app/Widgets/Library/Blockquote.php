<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A quotation.
 */
class Blockquote extends WidgetDefinition
{
    public string $name = 'blockquote';

    public ?string $label = 'Blockquote';

    public ?string $category = 'general';

    public function schema(Schema $s): Schema
    {
        return $s
            ->richText('content')->required()->label('Quote')
            ->string('author')->maxLength(200)->label('Author')
            ->url('cite')->label('Source URL');
    }

    public function example(): array
    {
        return ['content' => '<p>Simplicity is the ultimate sophistication.</p>', 'author' => 'Leonardo da Vinci'];
    }
}
