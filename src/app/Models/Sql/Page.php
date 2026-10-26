<?php 
namespace Andmarruda\Lpb\Models\Sql;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\ValueObjects\Slug;

class Page extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lpb_pages';

    protected $fillable = [
        'title',
        'extra_css',
        'extra_js',
        'slug',
        'status',
        'template_id',
    ];

    protected $casts = [
        'status' => PublishState::class,
        'extra_css' => 'string',
        'extra_js' => 'string',
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
     * Template relationship
     *
     * @return BelongsTo
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id', 'id');
    }

    /**
     * Blocks relationship
     *
     * @return HasMany
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'page_id', 'id')->orderBy('order')->orderBy('id');
    }

    /**
     * Metatags relationship
     * 
     * @return HasMany
     */
    public function metatags(): HasMany
    {
        return $this->hasMany(Metatag::class, 'lpb_page_id', 'id');
    }

    /**
     * Scope Published pages
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopePublished($query)
    {
        return $query->where('status', PublishState::Published->value);
    }

    /**
     * Scope by Slug
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Scope by draft status
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeDrafts($query)
    {
        return $query->where('status', PublishState::Draft->value);
    }
}
