<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Buttons that share the current page.
 */
class ShareButtons extends WidgetDefinition
{
    public string $name = 'share-buttons';

    public ?string $label = 'Share buttons';

    public ?string $category = 'marketing';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('networks', fn (Schema $i) => $i->enum('network', ['facebook', 'x', 'linkedin', 'whatsapp', 'telegram', 'pinterest', 'reddit', 'email'])->required())->required()->label('Networks')
            ->enum('shape', ['rounded', 'square', 'circle'])->default('rounded')->label('Shape')
            ->boolean('labels')->default(true)->label('Show labels');
    }

    public function example(): array
    {
        return ['networks' => [['network' => 'facebook'], ['network' => 'x'], ['network' => 'whatsapp']]];
    }
}
