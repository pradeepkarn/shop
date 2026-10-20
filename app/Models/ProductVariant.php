<?php

namespace App\Models;

use App\Enums\VariantQcStatus;
use App\Services\UpidGenerator;
use App\Services\VariantKeyNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property string $upid
 * @property string $variant_key
 * @property array<string, mixed> $variant_attributes
 * @property string|null $barcode
 * @property float|null $weight
 * @property array<string, mixed>|null $dimensions
 * @property VariantQcStatus $qc_status
 * @property string|null $qc_notes
 * @property Product $product
 * @property Collection<int, ProductImage> $images
 * @property Collection<int, Listing> $listings
 */
class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'upid',
        'variant_key',
        'variant_attributes',
        'barcode',
        'weight',
        'dimensions',
        'qc_status',
        'qc_notes',
    ];

    protected function casts(): array
    {
        return [
            'variant_attributes' => 'array',
            'dimensions' => 'array',
            'weight' => 'float',
            'qc_status' => VariantQcStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductVariant $variant) {
            if (blank($variant->upid)) {
                $variant->upid = UpidGenerator::generate();
            }

            if (blank($variant->variant_key) && ! empty($variant->variant_attributes)) {
                $variant->variant_key = VariantKeyNormalizer::normalize($variant->variant_attributes);
            }
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_variant_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'product_variant_id');
    }

    public function submitForQc(): void
    {
        $this->update(['qc_status' => VariantQcStatus::QcPending]);
    }

    public function approve(?string $notes = null): void
    {
        $this->update([
            'qc_status' => VariantQcStatus::Approved,
            'qc_notes' => $notes,
        ]);
    }

    public function reject(string $notes): void
    {
        $this->update([
            'qc_status' => VariantQcStatus::Rejected,
            'qc_notes' => $notes,
        ]);
    }
}
