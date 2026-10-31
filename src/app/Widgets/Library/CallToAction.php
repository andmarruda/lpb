<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A title, text and button over an image.
 */
class CallToAction extends WidgetDefinition
{
    public string $name = 'call-to-action';

    public ?string $label = 'Call to action';

    public ?string $category = 'marketing';

    public function schema(Schema $s): Schema
    {
        return $s
            ->media('image')->label('Background image')
            ->string('title')->required()->maxLength(200)->label('Title')
            ->richText('description')->label('Description')
            ->string('button_text')->maxLength(120)->label('Button text')
            ->link('link')->label('Link')
            ->enum('layout', ['classic', 'cover'])->default('cover')->label('Layout');
    }

    public function example(): array
    {
        return ['title' => 'Ready to start?', 'description' => '<p>Create your first page today.</p>', 'button_text' => 'Sign up', 'link' => ['url' => '/signup']];
    }
}
