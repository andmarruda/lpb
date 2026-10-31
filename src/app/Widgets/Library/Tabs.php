<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Content in tabs.
 */
class Tabs extends WidgetDefinition
{
    public string $name = 'tabs';

    public ?string $label = 'Tabs';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('items', fn (Schema $i) => $i->string('title')->required()->maxLength(120)->richText('content'))->required()->min(1)->label('Tabs')
            ->enum('orientation', ['horizontal', 'vertical'])->default('horizontal')->label('Orientation')
            ->integer('active')->min(0)->default(0)->label('Initially open tab');
    }

    public function example(): array
    {
        return ['items' => [['title' => 'Overview', 'content' => '<p>First tab.</p>'], ['title' => 'Details', 'content' => '<p>Second tab.</p>']]];
    }
}
