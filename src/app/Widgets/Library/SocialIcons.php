<?php
namespace Andmarruda\Lpb\Widgets\Library;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Links to social profiles.
 */
class SocialIcons extends WidgetDefinition
{
    public string $name = 'social-icons';

    public ?string $label = 'Social icons';

    public ?string $category = 'basic';

    public function schema(Schema $s): Schema
    {
        return $s
            ->array('items', fn (Schema $i) => $i->enum('network', ['facebook', 'x', 'instagram', 'linkedin', 'youtube', 'tiktok', 'github', 'whatsapp', 'telegram', 'pinterest', 'reddit', 'email'])->required()->url('url')->required())->required()->label('Networks')
            ->enum('shape', ['rounded', 'square', 'circle'])->default('rounded')->label('Shape')
            ->integer('size')->min(12)->max(120)->default(32)->label('Size (px)');
    }

    public function example(): array
    {
        return ['items' => [['network' => 'instagram', 'url' => 'https://instagram.com/acme'], ['network' => 'linkedin', 'url' => 'https://linkedin.com/company/acme']]];
    }
}
