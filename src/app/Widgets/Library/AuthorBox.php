<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A short presentation of a person.
 */
class AuthorBox extends WidgetDefinition
{
    public string $name = 'author-box';

    public ?string $label = 'Author box';

    public ?string $category = 'general';

    public function schema(Schema $s): Schema
    {
        return $s
            ->media('image')->label('Photo')
            ->string('name')->required()->maxLength(120)->label('Name')
            ->string('role')->maxLength(120)->label('Title')
            ->richText('bio')->label('Biography')
            ->link('link')->label('Link')
            ->string('link_text')->maxLength(60)->label('Link text');
    }

    public function example(): array
    {
        return ['name' => 'Ana Souza', 'role' => 'Editor', 'bio' => '<p>Writes about the web.</p>', 'link' => ['url' => '/team/ana'], 'link_text' => 'All posts'];
    }
}
