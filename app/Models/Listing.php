<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\SellerStatus;
use App\Enums\VariantQcStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $seller_profile_id
 * @property int $product_variant_id
 * @property string $sku
 * @property int $mrp In paise
 * @property int $selling_price In paise
 * @property int $stock
 * @property ListingStatus $status
 * @property Carbon|null $deleted_at
 * @property SellerProfile $sellerProfile
 * @property ProductVariant $variant
 */
class Listing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'seller_profile_id',
        'product_variant_id',
        'sku',
        'mrp',
        'selling_price',
        'stock',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'mrp' => 'integer',
            'selling_price' => 'integer',
            'stock' => 'integer',
            'status' => ListingStatus::class,
        ];
    }

    /**
     * @return BelongsTo<SellerProfile, $this>
     */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function mrpInRupees(): float
    {
        return $this->mrp / 100;
    }

    public function sellingPriceInRupees(): float
    {
        return $this->selling_price / 100;
    }

    /**
     * A listing is publicly visible on a product page if and only if:
     * seller_profile is approved + variant is approved + listing is active + stock > 0.
     */
    public function isPubliclyVisible(): bool
    {
        if ($this->trashed()) {
            return false;
        }

        if ($this->status !== ListingStatus::Active || $this->stock <= 0) {
            return false;
        }

        $variant = $this->relationLoaded('variant') ? $this->variant : $this->variant()->first();
        if (! $variant || $variant->qc_status !== VariantQcStatus::Approved) {
            return false;
        }

        $seller = $this->relationLoaded('sellerProfile') ? $this->sellerProfile : $this->sellerProfile()->first();
        if (! $seller || $seller->status !== SellerStatus::Approved) {
            return false;
        }

        return true;
    }

    public function activate(): void
    {
        $this->update(['status' => ListingStatus::Active]);
    }

    public function deactivate(): void
    {
        $this->update(['status' => ListingStatus::Inactive]);
    }

    public function archive(): void
    {
        $this->update(['status' => ListingStatus::Archived]);
        $this->delete();
    }

    /**
     * Upsert helper: if an archived listing with this (seller, variant) or (seller, sku) exists,
     * restore it with updated values.
     *
     * @param  array{seller_profile_id: int, product_variant_id: int, sku: string, mrp: int, selling_price: int, stock: int, status?: ListingStatus}  $attributes
     */
    public static function createOrRestore(array $attributes): self
    {
        $existing = static::withTrashed()
            ->where('seller_profile_id', $attributes['seller_profile_id'])
            ->where(function ($q) use ($attributes) {
                $q->where('product_variant_id', $attributes['product_variant_id'])
                    ->orWhere('sku', $attributes['sku']);
            })
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update($attributes);

            return $existing;
        }

        return static::create($attributes);
    }
}
