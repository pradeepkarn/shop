<?php

namespace App\Livewire\Storefront;

use App\Checkout\Models\CheckoutIntent;
use App\Checkout\Services\CheckoutIntentService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.storefront')] class extends Component {
    public string $intentId;

    public function mount(string $intent): void
    {
        $this->intentId = $intent;

        $intentModel = $this->intent;
        if (! $intentModel) {
            abort(404, 'Checkout intent not found.');
        }

        if ($intentModel->user_id !== auth()->id()) {
            abort(403, 'Unauthorized checkout access.');
        }

        if ($intentModel->isExpired()) {
            session()->flash('cart_error', 'Your checkout session has expired. Please review your items and try again.');
            $this->redirect($intentModel->getExpiredRedirectUrl());
        }
    }

    #[Computed]
    public function intent(): ?CheckoutIntent
    {
        return CheckoutIntent::find($this->intentId);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function evaluation(): array
    {
        $intentModel = $this->intent;
        if (! $intentModel) {
            return [];
        }

        return app(CheckoutIntentService::class)->evaluateIntent($intentModel);
    }

    public function increaseQty(int $listingId, CheckoutIntentService $service): void
    {
        $intentModel = $this->intent;
        if (! $intentModel || $intentModel->isExpired()) {
            session()->flash('cart_error', 'This checkout session has expired.');
            $this->redirect(route('cart'));
            return;
        }

        $line = collect($intentModel->lines)->firstWhere('listing_id', $listingId);
        $currentQty = $line ? (int) $line['qty'] : 1;

        $service->updateLineQty($intentModel, $listingId, $currentQty + 1);
        unset($this->intent);
        unset($this->evaluation);
    }

    public function decreaseQty(int $listingId, CheckoutIntentService $service): void
    {
        $intentModel = $this->intent;
        if (! $intentModel || $intentModel->isExpired()) {
            session()->flash('cart_error', 'This checkout session has expired.');
            $this->redirect(route('cart'));
            return;
        }

        $line = collect($intentModel->lines)->firstWhere('listing_id', $listingId);
        $currentQty = $line ? (int) $line['qty'] : 1;

        if ($currentQty > 1) {
            $service->updateLineQty($intentModel, $listingId, $currentQty - 1);
            unset($this->intent);
            unset($this->evaluation);
        }
    }

    public function removeLine(int $listingId, CheckoutIntentService $service): void
    {
        $intentModel = $this->intent;
        if (! $intentModel || $intentModel->isExpired()) {
            session()->flash('cart_error', 'This checkout session has expired.');
            $this->redirect(route('cart'));
            return;
        }

        $service->removeLine($intentModel, $listingId);
        unset($this->intent);
        unset($this->evaluation);

        if (empty($intentModel->fresh()->lines)) {
            session()->flash('cart_error', 'All items were removed from this checkout session.');
            $this->redirect(route('cart'));
        }
    }
}; ?>

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 pb-24 sm:pb-8">
    <!-- Breadcrumb -->
    <nav class="mb-6 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
        <a href="{{ route('products.index') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200" wire:navigate>Home</a>
        <span>/</span>
        <a href="{{ route('cart') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200" wire:navigate>Cart</a>
        <span>/</span>
        <span class="truncate font-medium text-zinc-800 dark:text-zinc-200">Order Summary</span>
    </nav>

    <!-- Header & Source Badge -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">
                Order Summary
            </h1>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                Review your items before proceeding to address & payment.
            </p>
        </div>
        <div>
            <span class="inline-flex items-center rounded-lg bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                Checkout Source: {{ $this->intent?->source === 'buy_now' ? 'Buy Now' : 'Shopping Cart' }}
            </span>
        </div>
    </div>

    @php
        $eval = $this->evaluation;
        $activeLines = $eval['active_lines'] ?? [];
        $unavailableLines = $eval['unavailable_lines'] ?? [];
        $priceChanges = $eval['price_changes'] ?? [];
        $summary = $eval['summary'] ?? [];
    @endphp

    <!-- Price Changes Notice -->
    @if (! empty($priceChanges))
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200">
            <p class="font-semibold">Price update notice</p>
            <p class="text-xs text-amber-800 dark:text-amber-300">Some items have updated live prices. Your summary reflects current prices.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <!-- Left: Items list -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Active Items Card -->
            <div class="rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden">
                <div class="border-b border-zinc-100 bg-zinc-50/50 px-6 py-3.5 text-xs font-bold uppercase tracking-wider text-zinc-600 dark:border-zinc-800 dark:bg-zinc-800/30 dark:text-zinc-400">
                    Items In Order ({{ count($activeLines) }})
                </div>

                @if (empty($activeLines))
                    <div class="p-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        No available items to checkout.
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($activeLines as $line)
                            @php
                                $unitPrice = (int) $line['unit_price'];
                                $mrp = (int) $line['mrp'];
                                $discountPct = $mrp > $unitPrice ? (int) round((($mrp - $unitPrice) / $mrp) * 100) : 0;
                            @endphp
                            <div class="p-6 flex flex-col sm:flex-row gap-5">
                                <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 dark:border-zinc-800 dark:bg-zinc-800">
                                    <img
                                        src="{{ $line['image'] ?: asset('storage/products/placeholder.svg') }}"
                                        alt="{{ $line['name'] }}"
                                        class="h-full w-full object-contain"
                                    />
                                </div>

                                <div class="flex-1 space-y-2">
                                    <h3 class="font-semibold text-zinc-900 dark:text-white text-base">
                                        {{ $line['name'] }}
                                    </h3>

                                    @if (! empty($line['seller_name']))
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                            Sold by <strong class="text-zinc-700 dark:text-zinc-300">{{ $line['seller_name'] }}</strong>
                                        </p>
                                    @endif

                                    <div class="flex items-baseline gap-2.5">
                                        <span class="text-lg font-bold text-zinc-900 dark:text-white">
                                            ₹{{ number_format($unitPrice / 100) }}
                                        </span>
                                        @if ($mrp > $unitPrice)
                                            <span class="text-xs text-zinc-400 line-through">
                                                ₹{{ number_format($mrp / 100) }}
                                            </span>
                                            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                                {{ $discountPct }}% Off
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Quantity Stepper & Remove Action -->
                                    <div class="pt-2 flex items-center gap-6">
                                        <div class="inline-flex items-center rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                                            <button
                                                type="button"
                                                wire:click="decreaseQty({{ $line['listing_id'] }})"
                                                wire:loading.attr="disabled"
                                                {{ $line['qty'] <= 1 ? 'disabled' : '' }}
                                                class="h-8 w-8 flex items-center justify-center text-zinc-600 hover:bg-zinc-100 disabled:opacity-30 disabled:cursor-not-allowed dark:text-zinc-300 dark:hover:bg-zinc-700 rounded-l-lg transition"
                                                aria-label="Decrease quantity"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                                                </svg>
                                            </button>
                                            <span class="w-10 text-center text-xs font-bold text-zinc-900 dark:text-white">
                                                {{ $line['qty'] }}
                                            </span>
                                            <button
                                                type="button"
                                                wire:click="increaseQty({{ $line['listing_id'] }})"
                                                wire:loading.attr="disabled"
                                                {{ $line['qty'] >= $line['max_qty'] ? 'disabled' : '' }}
                                                class="h-8 w-8 flex items-center justify-center text-zinc-600 hover:bg-zinc-100 disabled:opacity-30 disabled:cursor-not-allowed dark:text-zinc-300 dark:hover:bg-zinc-700 rounded-r-lg transition"
                                                aria-label="Increase quantity"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                            </button>
                                        </div>

                                        <button
                                            type="button"
                                            wire:click="removeLine({{ $line['listing_id'] }})"
                                            wire:confirm="Remove this item from your order?"
                                            wire:loading.attr="disabled"
                                            class="text-xs font-bold uppercase tracking-wider text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400 transition"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Unavailable Items Card (Partitioned) -->
            @if (! empty($unavailableLines))
                <div class="rounded-2xl border border-red-200 bg-red-50/30 dark:border-red-900/40 dark:bg-red-950/20 shadow-xs overflow-hidden">
                    <div class="border-b border-red-100 dark:border-red-900/40 bg-red-50/80 px-6 py-3.5 text-xs font-bold uppercase tracking-wider text-red-700 dark:bg-red-950/50 dark:text-red-300">
                        Unavailable Items (Excluded from checkout total)
                    </div>

                    <div class="divide-y divide-red-100 dark:divide-red-900/30">
                        @foreach ($unavailableLines as $line)
                            <div class="p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl border border-zinc-200 bg-white p-2 dark:border-zinc-800 dark:bg-zinc-800 opacity-60">
                                        <img
                                            src="{{ $line['image'] ?: asset('storage/products/placeholder.svg') }}"
                                            alt="{{ $line['name'] }}"
                                            class="h-full w-full object-contain"
                                        />
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-zinc-800 dark:text-zinc-200 text-sm">
                                            {{ $line['name'] }}
                                        </h4>
                                        <p class="text-xs font-semibold text-red-600 dark:text-red-400">
                                            {{ $line['reason'] }}
                                        </p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    wire:click="removeLine({{ $line['listing_id'] }})"
                                    wire:loading.attr="disabled"
                                    class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-800 dark:bg-zinc-900 dark:text-red-300 transition"
                                >
                                    Remove
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Right: Sticky Order Summary & Price Details -->
        <div class="lg:col-span-1">
            <div class="sticky top-20 rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-5">
                <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 border-b border-zinc-100 dark:border-zinc-800 pb-3">
                    Price Details
                </h2>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between text-zinc-700 dark:text-zinc-300">
                        <span>Price ({{ $summary['items_count'] ?? 0 }} {{ ($summary['items_count'] ?? 0) === 1 ? 'item' : 'items' }})</span>
                        <span>₹{{ number_format(($summary['mrp_total'] ?? 0) / 100) }}</span>
                    </div>

                    <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                        <span>Discount</span>
                        <span>−₹{{ number_format(($summary['discount_total'] ?? 0) / 100) }}</span>
                    </div>

                    <div class="flex justify-between text-zinc-700 dark:text-zinc-300">
                        <span>Delivery Charges</span>
                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">Free</span>
                    </div>

                    <div class="border-t border-dashed border-zinc-200 pt-3 dark:border-zinc-800 flex justify-between text-base font-bold text-zinc-900 dark:text-white">
                        <span>Total Payable</span>
                        <span>₹{{ number_format(($summary['payable_total'] ?? 0) / 100) }}</span>
                    </div>
                </div>

                @if (($summary['savings'] ?? 0) > 0)
                    <div class="rounded-xl bg-emerald-50 p-3 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                        You will save ₹{{ number_format($summary['savings'] / 100) }} on this order
                    </div>
                @endif

                <!-- Phase 2 Entry Point: Disabled Continue Button -->
                <div class="pt-2 space-y-3">
                    <button
                        type="button"
                        disabled
                        class="w-full rounded-xl bg-zinc-200 px-6 py-3.5 text-center text-sm font-bold text-zinc-400 cursor-not-allowed dark:bg-zinc-800 dark:text-zinc-500"
                    >
                        Address & payment coming soon
                    </button>
                    <p class="text-center text-xs text-zinc-400">
                        Phase 1 summary preview. Order creation unlocks in Phase 2.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

