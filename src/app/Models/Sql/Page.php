<?php 
namespace Andmarruda\Lpb\Models\Sql;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Andmarruda\Lpb\Contracts\PageRepositoryInterface;

class Page extends Model implements PageRepositoryInterface
{
    use HasFactory, HasUuids;

    protected $table = 'lpb_pages';

    protected $fillable = [
        'title',
        'extra_css',
        'extra_js',
        'slug',
        'status',
        'theme',
    ];

    protected $casts = [
        'theme' => 'boolean',
        'extra_css' => 'string',
        'extra_js' => 'string',
    ];

    /**
     * Widgets relationship
     * 
     * @return HasMany
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(PageWidget::class, 'page_id', 'id')->orderBy('order');
    }

    /**
     * Metatags relationship
     * 
     * @return HasMany
     */
    public function metatags(): HasMany
    {
        return $this->hasMany(PageMetatag::class, 'page_id', 'id');
    }

    /**
     * Scope Published pages
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
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
        return $query->where('status', 'draft');
    }
}
