<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A title or heading.
 */
class Heading extends WidgetDefinition
{
    public string $name = 'heading';

    public ?string $label = 'Heading';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('text')->required()->maxLength(500)->label('Text')
            ->enum('tag', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span'])->default('h2')->label('HTML tag')
            ->enum('align', ['left', 'center', 'right', 'justify'])->label('Alignment')
            ->link('link')->label('Link');
    }

    public function example(): array
    {
        return ['text' => 'Build pages faster', 'tag' => 'h2', 'align' => 'center'];
    }
}
