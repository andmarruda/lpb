<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Testimonials in a carousel (also used for reviews).
 */
class TestimonialCarousel extends WidgetDefinition
{
    public string $name = 'testimonial-carousel';

    public ?string $label = 'Testimonial carousel';

    public ?string $category = 'marketing';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('items', fn (Schema $i) => $i->string('content')->required()->maxLength(2000)->string('name')->maxLength(120)->string('role')->maxLength(120)->media('image')->number('rating')->min(0)->max(5))->required()->label('Testimonials')
            ->integer('per_view')->min(1)->max(4)->default(1)->label('Per view')
            ->boolean('autoplay')->default(true)->label('Autoplay')
            ->integer('interval')->min(1000)->max(60000)->default(6000)->label('Interval (ms)');
    }

    public function example(): array
    {
        return ['items' => [['content' => 'Great product!', 'name' => 'Ana'], ['content' => 'Support is amazing.', 'name' => 'Bruno', 'rating' => 5]]];
    }
}
