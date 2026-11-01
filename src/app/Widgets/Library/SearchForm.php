<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A search box that submits to a URL of the application.
 */
class SearchForm extends WidgetDefinition
{
    public string $name = 'search-form';

    public ?string $label = 'Search form';

    public ?string $category = 'forms';

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('action')->required()->maxLength(2048)->default('/search')->label('Search URL')
            ->string('param')->pattern('^[A-Za-z][A-Za-z0-9_]*$')->maxLength(40)->default('q')->label('Query parameter')
            ->string('placeholder')->maxLength(200)->default('Search…')->label('Placeholder')
            ->string('button_text')->maxLength(60)->label('Button text');
    }

    public function example(): array
    {
        return ['action' => '/search', 'placeholder' => 'Search the site'];
    }
}
