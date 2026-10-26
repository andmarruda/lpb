<?php
namespace Andmarruda\Lpb\Models\Mongo;

use Andmarruda\Lpb\Models\Concerns\UsesConfiguredConnection;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Andmarruda\Lpb\ValueObjects\Slug;

class Menu extends Model
{
    use UsesConfiguredConnection;

    protected $connection = 'mongodb';
    protected $table = 'lpb_menus';

    // items is written by the repository, which assigns item ids.
    protected $fillable = [
        'name',
        'slug',
        'location',
    ];

    /**
     * Validate the slug through the Slug value object on write
     *
     * The explicit getter is required: without one, laravel-mongodb treats
     * the slug() method as a relation and $model->slug returns null.
     * Object caching is disabled so a Slug passed on write reads back as a string.
     *
     * @return Attribute
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: fn (Slug|string $value) => Slug::from((string) $value)->value(),
        )->withoutObjectCaching();
    }
}
