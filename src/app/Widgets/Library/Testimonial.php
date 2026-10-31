<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * A quote from a customer.
 */
class Testimonial extends WidgetDefinition
{
    public string $name = 'testimonial';

    public ?string $label = 'Testimonial';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->richText('content')->required()->label('Content')
            ->string('name')->maxLength(120)->label('Name')
            ->string('role')->maxLength(120)->label('Title')
            ->media('image')->label('Photo')
            ->number('rating')->min(0)->max(5)->label('Rating');
    }

    public function example(): array
    {
        return ['content' => '<p>The best decision we made this year.</p>', 'name' => 'Ana Souza', 'role' => 'CEO, Acme'];
    }
}
