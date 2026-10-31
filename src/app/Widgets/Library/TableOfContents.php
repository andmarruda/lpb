<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Links to the headings of the page.
 */
class TableOfContents extends WidgetDefinition
{
    public string $name = 'table-of-contents';

    public ?string $label = 'Table of contents';

    public ?string $category = 'navigation';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('title')->maxLength(200)->default('Table of contents')->label('Title')
            ->strings('headings')->label('Heading levels')->help('e.g. h2, h3')
            ->boolean('collapsible')->default(false)->label('Collapsible')
            ->boolean('numbered')->default(false)->label('Numbered');
    }

    public function example(): array
    {
        return ['title' => 'On this page', 'headings' => ['h2', 'h3']];
    }
}
