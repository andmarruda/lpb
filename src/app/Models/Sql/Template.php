<?php
namespace Andmarruda\Lpb\Models\Sql;

use Andmarruda\Lpb\Models\Concerns\UsesConfiguredConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Andmarruda\Lpb\Casts\SeoCast;
use Andmarruda\Lpb\ValueObjects\Slug;
use Andmarruda\Lpb\ValueObjects\TemplateStatus;

class Template extends Model
{
    use HasUuids, UsesConfiguredConnection;

    protected $table = 'lpb_templates';

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
        'seo' => SeoCast::class,
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
     * Pages relationship
     *
     * @return HasMany
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class, 'template_id', 'id');
    }

    /**
     * Scope active templates
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeActive($query)
    {
        return $query->where('status', TemplateStatus::Active->value);
    }

    /**
     * Scope the default template
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
     * @return void
     */
    public function setDefault(): void
    {
        $this->getConnection()->transaction(function () {
            static::query()->whereKeyNot($this->getKey())->where('is_default', true)->update(['is_default' => false]);
            $this->is_default = true;
            $this->save();
        });
    }
}
