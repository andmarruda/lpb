<?php
namespace Andmarruda\Lpb\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\ValueObjects\Slug;

class Page extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'lpb_pages';

    protected $fillable = [
        'title',
        'extra_css',
        'extra_js',
        'slug',
        'status',
        'theme',
        'metatags',
        'widgets',
    ];

    protected $casts = [
        'theme' => 'boolean',
        'status' => PublishState::class,
        'metatags' => 'array',
        'widgets' => 'array',
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
     * Published scope
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopePublished($query)
    {
        return $query->where('status', PublishState::Published->value);
    }

    /**
     * By Slug scope
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Draft scope
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeDraft($query)
    {
        return $query->where('status', PublishState::Draft->value);
    }

    /**
     * Add a widget to the page
     * 
     * @param array $widget
     * @return void
     */
    public function addWidget(array $widget): void
    {
        $widgets = $this->widgets ?? [];
        $widgets[] = $widget;
        $this->widgets = $widgets;
        $this->save();
    }

    /**
     * Update a widget at a specific index
     * 
     * @param int $index
     * @param array $data
     * @return void
     */
    public function updateWidget(int $index, array $data): void
    {
        $widgets = $this->widgets ?? [];
        if (isset($widgets[$index])) {
            $widgets[$index] = array_merge($widgets[$index], $data);
            $this->widgets = $widgets;
            $this->save();
        }
    }

    /**
     * Remove a widget at a specific index
     * 
     * @param int $index
     * @return void
     */
    public function removeWidget(int $index): void
    {
        $widgets = $this->widgets ?? [];
        if (isset($widgets[$index])) {
            array_splice($widgets, $index, 1);
            $this->widgets = $widgets;
            $this->save();
        }
    }

    /**
     * Add a metatag to the page
     * 
     * @param string $name
     * @param string $content
     * @return void
     */
    public function addMetatag(string $name, string $content): void
    {
        $metatags = $this->metatags ?? [];
        $metatags[] = ['name' => $name, 'content' => $content];
        $this->metatags = $metatags;
        $this->save();
    }

    /**
     * Set or update a metatag
     * 
     * @param string $name
     * @param string $content
     * @return void
     */
    public function setMetatag(string $name, string $content): void
    {
        $metatags = $this->metatags ?? [];
        $found = false;
        foreach ($metatags as &$tag) {
            if ($tag['name'] === $name) {
                $tag['content'] = $content;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $metatags[] = ['name' => $name, 'content' => $content];
        }

        $this->metatags = $metatags;
        $this->save();
    }

    /**
     * Get a metatag content by name
     * 
     * @param string $name
     * @return string|null
     */
    public function getMetatag(string $name): ?string
    {
        $metatags = $this->metatags ?? [];
        foreach ($metatags as $tag) {
            if ($tag['name'] === $name) {
                return $tag['content'];
            }
        }
        return null;
    }
}
