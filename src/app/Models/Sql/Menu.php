<?php
namespace Andmarruda\Lpb\Models\Sql;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Andmarruda\Lpb\ValueObjects\Slug;

class Menu extends Model
{
    protected $table = 'lpb_menus';

    protected $fillable = [
        'name',
        'slug',
        'location',
    ];

    /**
     * Validate the slug through the Slug value object on write
     *
     * @return Attribute
     */
    protected function slug(): Attribute
    {
        return Attribute::set(fn (Slug|string $value) => Slug::from((string) $value)->value());
    }

    /**
     * Get the menu items as a flat list ordered by order, then insertion.
     *
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_id')->orderBy('order')->orderBy('id');
    }
}
