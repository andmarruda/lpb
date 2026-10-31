<?php

namespace Andmarruda\Lpb\Tests\Fixtures\Widgets;

use Andmarruda\Lpb\Widgets\Schema;
use Andmarruda\Lpb\Widgets\WidgetDefinition;

/**
 * Version 1 stored "heading", version 2 renamed it to "title" and
 * version 3 added a required "size".
 */
class Card extends WidgetDefinition
{
    public string $name = 'card';

    public int $version = 3;

    public function schema(Schema $s): Schema
    {
        return $s
            ->string('title')->required()
            ->enum('size', ['sm', 'md', 'lg'])->required();
    }

    public function upgrade(int $from, array $config): array
    {
        if ($from === 1) {
            $config['title'] = $config['heading'] ?? '';
            unset($config['heading']);
        }
        if ($from === 2) {
            $config['size'] ??= 'md';
        }
        return $config;
    }
}
