<?php
namespace Andmarruda\Lpb\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Andmarruda\Lpb\Widgets\WidgetRegistry widgets()
 * @method static \Symfony\Component\HttpFoundation\Response view(string $slug)
 * @method static \Symfony\Component\HttpFoundation\Response inertia(string $slug)
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
