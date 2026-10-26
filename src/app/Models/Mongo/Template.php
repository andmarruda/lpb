<?php
namespace Andmarruda\Lpb\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Andmarruda\Lpb\Casts\SeoCast;
use Andmarruda\Lpb\ValueObjects\Slug;
use Andmarruda\Lpb\ValueObjects\TemplateStatus;

class Template extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'lpb_templates';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'view_path',
        'is_default',
        'status',
        'seo',
    ];

    protected $attributes = [
        'is_default' => false,
        'status' => 'active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'status' => TemplateStatus::class,
        'seo' => SeoCast::class . ':document',
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

    /**
     * Active scope
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeActive($query)
    {
        return $query->where('status', TemplateStatus::Active->value);
    }

    /**
     * Default scope
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Make this the only default template
     *
     * Standalone MongoDB servers have no transactions, so this template is
     * marked first and the others are cleared afterwards.
     *
     * @return void
     */
    public function setDefault(): void
    {
        $this->is_default = true;
        $this->save();

        static::query()->where('id', '!=', $this->getKey())->where('is_default', true)->update(['is_default' => false]);
    }
}
