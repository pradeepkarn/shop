<?php

namespace App\Livewire\Storefront;

use App\Enums\ListingStatus;
use App\Enums\ProductImageStatus;
use App\Enums\ProductQcStatus;
use App\Enums\SellerStatus;
use App\Enums\VariantQcStatus;
use App\Models\Listing;
use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.storefront')] class extends Component {
    public string $slug;
    public string $upid;

    public function mount(string $slug, string $upid): void
    {
        $this->slug = $slug;
        $this->upid = $upid;

        if (! $this->product || ! $this->currentVariant) {
            abort(404, 'Product or variant not found');
        }
    }

    #[Computed]
    public function product(): ?Product
    {
        return Product::where('slug', $this->slug)
            ->where('qc_status', ProductQcStatus::Approved)
            ->with([
                'brand',
                'vertical',
                'images' => fn ($q) => $q->where('status', ProductImageStatus::Live)->orderBy('sort_order'),
                'variants' => fn ($q) => $q->where('qc_status', VariantQcStatus::Approved)->with('listings.sellerProfile'),
                'buyBoxListing.sellerProfile',
            ])
            ->first();
    }

    #[Computed]
    public function currentVariant(): ?ProductVariant
    {
        return ProductVariant::where('upid', $this->upid)
            ->where('product_id', $this->product?->id)
            ->where('qc_status', VariantQcStatus::Approved)
            ->with(['images', 'listings' => fn ($q) => $q->where('status', ListingStatus::Active)->whereHas('sellerProfile', fn ($s) => $s->where('status', SellerStatus::Approved))])
            ->first();
    }

    #[Computed]
    public function bestListing(): ?Listing
    {
        $variant = $this->currentVariant;
        if (! $variant) {
            return null;
        }

        // 1. In stock active listing from approved seller
        $inStockListing = $variant->listings
            ->where('stock', '>', 0)
            ->sortBy('selling_price')
            ->first();

        if ($inStockListing) {
            return $inStockListing;
        }

        // 2. Out of stock fallback
        return $variant->listings
            ->sortBy('selling_price')
            ->first();
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <!-- Breadcrumb -->
    <nav class="mb-6 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
        <a href="{{ route('products.index') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200" wire:navigate>Catalog</a>
        @if ($this->product->vertical)
            <span>/</span>
            <a href="{{ route('products.index', ['vertical' => $this->product->vertical->slug]) }}" class="hover:text-zinc-800 dark:hover:text-zinc-200" wire:navigate>
                {{ $this->product->vertical->name }}
            </a>
        @endif
        <span>/</span>
        <span class="truncate font-medium text-zinc-800 dark:text-zinc-200">{{ $this->product->title }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-10 md:grid-cols-2">
        <!-- Image Section -->
        <div>
            @php
                $primaryImg = $this->product->images->first(fn ($img) => $img->is_primary && $img->status === ProductImageStatus::Live)
                    ?? $this->product->images->first();
                $imgUrl = $primaryImg ? $primaryImg->url() : asset('storage/products/placeholder.svg');
            @endphp
            <div class="aspect-square w-full overflow-hidden rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <img src="{{ $imgUrl }}" alt="{{ $this->product->title }}" class="h-full w-full object-contain" />
            </div>
        </div>

        <!-- Details Section -->
        <div class="space-y-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-1">
                    {{ $this->product->brand?->name ?? 'Brand' }}
                </p>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">
                    {{ $this->product->title }}
                </h1>
            </div>

            <!-- Pricing Box -->
            @php
                $listing = $this->bestListing ?? $this->product->buyBoxListing;
                $inStock = $listing && (int) $listing->stock > 0;
                $sellingPrice = $listing ? $listing->selling_price : $this->product->min_price;
                $mrp = $listing ? $listing->mrp : $this->product->buy_box_mrp;
                $discountPercent = $mrp && $mrp > $sellingPrice ? (int) round((($mrp - $sellingPrice) / $mrp) * 100) : 0;
            @endphp

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-4">
                <div class="flex items-baseline gap-3">
                    <span class="text-3xl font-extrabold text-zinc-900 dark:text-white">
                        ₹{{ number_format((int) $sellingPrice / 100) }}
                    </span>
                    @if ($mrp && $mrp > $sellingPrice)
                        <span class="text-base text-zinc-400 line-through">
                            ₹{{ number_format((int) $mrp / 100) }}
                        </span>
                        <span class="inline-flex items-center rounded-md bg-emerald-600 px-2 py-0.5 text-xs font-semibold text-white">
                            {{ $discountPercent }}% OFF
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    @if ($inStock)
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            In Stock
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 dark:text-red-400">
                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                            Out of stock
                        </span>
                    @endif

                    @if ($listing?->sellerProfile)
                        <span class="text-xs text-zinc-500">&middot;</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                            Sold by <strong class="text-zinc-800 dark:text-zinc-200">{{ $listing->sellerProfile->store_name }}</strong>
                        </span>
                    @endif
                </div>
            </div>

            <!-- Available Variants -->
            @if ($this->product->variants->count() > 1)
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">
                        Available Variants
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->product->variants as $variant)
                            @php
                                $isCurrent = $variant->upid === $this->upid;
                                $attrsSummary = collect($variant->variant_attributes ?? [])->map(fn($v, $k) => "$k: $v")->implode(', ');
                            @endphp
                            <a
                                href="{{ route('product.show', ['slug' => $this->product->slug, 'upid' => $variant->upid]) }}"
                                class="rounded-lg border px-3 py-1.5 text-xs font-medium transition {{ $isCurrent ? 'border-amber-500 bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-200' : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' }}"
                                wire:navigate
                            >
                                {{ $attrsSummary ?: 'Default' }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Highlights -->
            @if (! empty($this->product->highlights))
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">Highlights</h3>
                    <ul class="list-disc pl-5 text-sm text-zinc-700 dark:text-zinc-300 space-y-1">
                        @foreach ($this->product->highlights as $highlight)
                            <li>{{ $highlight }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Description -->
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">Description</h3>
                <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                    {{ $this->product->description }}
                </p>
            </div>
        </div>
    </div>
</div>

