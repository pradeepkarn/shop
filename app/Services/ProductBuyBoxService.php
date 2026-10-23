<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Enums\ProductQcStatus;
use App\Enums\SellerStatus;
use App\Enums\VariantQcStatus;
use App\Models\Listing;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductBuyBoxService
{
    /**
     * Refresh the buy box columns for a single product.
     */
    public function refresh(Product|int $product): void
    {
        $productId = $product instanceof Product ? $product->id : $product;
        $productModel = $product instanceof Product ? $product : Product::find($productId);

        if (! $productModel) {
            return;
        }

        $buyBoxListing = null;

        // Buy box is only selected if the product itself is QC approved
        if ($productModel->qc_status === ProductQcStatus::Approved) {
            $baseListingQuery = Listing::query()
                ->where('status', ListingStatus::Active)
                ->whereHas('variant', function (Builder $q) use ($productId) {
                    $q->where('product_id', $productId)
                        ->where('qc_status', VariantQcStatus::Approved);
                })
                ->whereHas('sellerProfile', function (Builder $q) {
                    $q->where('status', SellerStatus::Approved);
                });

            // Priority 1: Cheapest in-stock active listing from an approved seller
            $buyBoxListing = (clone $baseListingQuery)
                ->where('stock', '>', 0)
                ->orderBy('selling_price', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            // Priority 2: Fallback to cheapest active listing (even if stock is 0)
            if (! $buyBoxListing) {
                $buyBoxListing = (clone $baseListingQuery)
                    ->orderBy('selling_price', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();
            }
        }

        if ($buyBoxListing) {
            $mrp = (int) $buyBoxListing->mrp;
            $sellingPrice = (int) $buyBoxListing->selling_price;
            $discountPercent = $mrp > $sellingPrice
                ? (int) round((($mrp - $sellingPrice) / $mrp) * 100)
                : 0;
            $inStock = (int) $buyBoxListing->stock > 0;

            $productModel->forceFill([
                'buy_box_listing_id' => $buyBoxListing->id,
                'min_price' => $sellingPrice,
                'buy_box_mrp' => $mrp,
                'discount_percent' => $discountPercent,
                'in_stock' => $inStock,
            ])->saveQuietly();
        } else {
            $productModel->forceFill([
                'buy_box_listing_id' => null,
                'min_price' => null,
                'buy_box_mrp' => null,
                'discount_percent' => 0,
                'in_stock' => false,
            ])->saveQuietly();
        }
    }

    /**
     * Refresh buy box summary for all products in chunks.
     *
     * @return int Number of products refreshed
     */
    public function refreshAll(): int
    {
        $count = 0;
        Product::query()->chunkById(100, function ($products) use (&$count) {
            foreach ($products as $product) {
                $this->refresh($product);
                $count++;
            }
        });

        return $count;
    }
}
