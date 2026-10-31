<?php
namespace Andmarruda\Lpb\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Andmarruda\Lpb\Widgets\WidgetRegistry widgets()
 *
 * @see \Andmarruda\Lpb\Lpb
 */
class Lpb extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Andmarruda\Lpb\Lpb::class;
    }
}
