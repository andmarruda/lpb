<?php
namespace Andmarruda\Lpb\Tree;

/**
 * Block types of format_version 1. Widgets render before child blocks in
 * both types.
 */
enum BlockType: string
{
    case Flex = 'flex';
    case Grid = 'grid';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
