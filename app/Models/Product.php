<?php

namespace App\Models;

use App\Enums\BrandStatus;
use App\Enums\ProductImageStatus;
use App\Enums\ProductQcStatus;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $vertical_id
 * @property int $brand_id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property array<int, string>|null $highlights
 * @property array<string, mixed>|null $specs
 * @property int|null $created_by
 * @property ProductQcStatus $qc_status
 * @property string|null $qc_notes
 * @property int|null $reviewed_by_admin_id
 * @property Carbon|null $reviewed_at
 * @property array<string, mixed>|null $pending_changes
 * @property Vertical|null $vertical
 * @property Brand|null $brand
 * @property SellerProfile|null $creator
 * @property Admin|null $reviewer
 * @property Collection<int, ProductVariant> $variants
 * @property Collection<int, ProductImage> $images
 */
class Product extends Model
{
    protected $fillable = [
        'vertical_id',
        'brand_id',
        'title',
        'slug',
        'description',
        'highlights',
        'specs',
        'created_by',
        'qc_status',
        'qc_notes',
        'reviewed_by_admin_id',
        'reviewed_at',
        'pending_changes',
    ];

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'specs' => 'array',
            'pending_changes' => 'array',
            'qc_status' => ProductQcStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (blank($product->slug)) {
                $brandName = $product->brand ? $product->brand->name : 'brand';
                $product->slug = Str::slug("{$brandName} {$product->title}");
            }
        });
    }

    /**
     * @return BelongsTo<Vertical, $this>
     */
    public function vertical(): BelongsTo
    {
        return $this->belongsTo(Vertical::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<SellerProfile, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'created_by');
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasManyThrough<Listing, ProductVariant, $this>
     */
    public function listings(): HasManyThrough
    {
        return $this->hasManyThrough(Listing::class, ProductVariant::class);
    }

    public function canonicalSlug(): string
    {
        $brandName = $this->brand ? $this->brand->name : 'brand';

        return Str::slug("{$brandName} {$this->title}");
    }

    public function submitForQc(): void
    {
        $this->update([
            'qc_status' => ProductQcStatus::QcPending,
        ]);
    }

    public function approve(Admin $admin, ?string $notes = null): void
    {
        if ($this->brand && $this->brand->status !== BrandStatus::Approved) {
            throw new DomainException('Cannot approve product while associated brand is pending review.');
        }

        $dataToUpdate = [
            'qc_status' => ProductQcStatus::Approved,
            'qc_notes' => $notes,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ];

        // Apply pending changes if present
        if (! empty($this->pending_changes)) {
            foreach ($this->pending_changes as $key => $val) {
                if (in_array($key, ['title', 'description', 'highlights', 'specs', 'brand_id'], true)) {
                    $dataToUpdate[$key] = $val;
                }
            }
            $dataToUpdate['pending_changes'] = null;
        }

        $this->update($dataToUpdate);

        // Regenerate canonical slug
        $this->refresh();
        $this->update(['slug' => $this->canonicalSlug()]);

        // Process image changes
        foreach ($this->images as $img) {
            if ($img->status === ProductImageStatus::PendingAdd) {
                $img->update(['status' => ProductImageStatus::Live]);
            } elseif ($img->status === ProductImageStatus::PendingRemove) {
                if ($img->path !== '' && Storage::disk('public')->exists($img->path)) {
                    Storage::disk('public')->delete($img->path);
                }
                $img->delete();
            }
        }
    }

    public function reject(Admin $admin, string $notes): void
    {
        $this->update([
            'qc_status' => ProductQcStatus::Rejected,
            'qc_notes' => $notes,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // Clean up pending add images and revert pending remove images
        foreach ($this->images as $img) {
            if ($img->status === ProductImageStatus::PendingAdd) {
                if ($img->path !== '' && Storage::disk('public')->exists($img->path)) {
                    Storage::disk('public')->delete($img->path);
                }
                $img->delete();
            } elseif ($img->status === ProductImageStatus::PendingRemove) {
                $img->update(['status' => ProductImageStatus::Live]);
            }
        }
    }
}
