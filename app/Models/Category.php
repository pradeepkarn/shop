<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property bool $is_active
 * @property Category|null $parent
 * @property Collection<int, Category> $children
 * @property Collection<int, Vertical> $verticals
 */
class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (blank($category->slug)) {
                $category->slug = static::generateUniqueSlug($category->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<Vertical, $this>
     */
    public function verticals(): HasMany
    {
        return $this->hasMany(Vertical::class);
    }

    public function isLeaf(): bool
    {
        return ! $this->children()->exists();
    }

    public function canHaveChildren(): bool
    {
        return ! $this->verticals()->exists();
    }

    public function canHaveVerticals(): bool
    {
        return $this->isLeaf();
    }

    /**
     * Breadcrumb chain of ancestors from root down to this category.
     *
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    public function breadcrumbs(): array
    {
        $trail = [];
        $current = $this;

        while ($current !== null) {
            array_unshift($trail, [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
            ]);
            $current = $current->parent;
        }

        return $trail;
    }

    public function breadcrumbString(string $separator = ' › '): string
    {
        return implode($separator, array_column($this->breadcrumbs(), 'name'));
    }

    public function deactivateWithChildren(): void
    {
        $this->update(['is_active' => false]);

        foreach ($this->children as $child) {
            $child->deactivateWithChildren();
        }
    }
}
