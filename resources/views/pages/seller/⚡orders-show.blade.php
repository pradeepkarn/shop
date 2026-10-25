<?php

namespace App\Livewire\Seller;

use App\Orders\Enums\SellerOrderStatus;
use App\Orders\Models\SellerOrder;
use App\Orders\Services\OrderService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.seller')] #[Title('Order Details')] class extends Component {
    public string $sellerOrderId;

    public bool $showCancelModal = false;

    public string $cancelReason = '';

    public function mount(string $sellerOrder): void
    {
        $this->sellerOrderId = $sellerOrder;

        $orderModel = $this->order;
        if (! $orderModel) {
            abort(404, 'Seller order not found.');
        }

        $this->authorize('view', $orderModel);
    }

    #[Computed]
    public function order(): ?SellerOrder
    {
        return SellerOrder::with(['order', 'items', 'statusHistories'])
            ->find($this->sellerOrderId);
    }

    public function confirmOrder(OrderService $orderService): void
    {
        $order = $this->order;
        if (! $order) {
            return;
        }

        $this->authorize('update', $order);

        try {
            $orderService->transitionSellerOrderStatus(
                $order,
                SellerOrderStatus::Confirmed,
                'seller',
                auth()->id(),
                'Order confirmed by seller'
            );

            session()->flash('order_success', 'Order marked as Confirmed.');
            unset($this->order);
        } catch (\DomainException $e) {
            session()->flash('order_error', $e->getMessage());
        }
    }

    public function markPacked(OrderService $orderService): void
    {
        $order = $this->order;
        if (! $order) {
            return;
        }

        $this->authorize('update', $order);

        try {
            $orderService->transitionSellerOrderStatus(
                $order,
                SellerOrderStatus::Packed,
                'seller',
                auth()->id(),
                'Order marked as Packed'
            );

            session()->flash('order_success', 'Order marked as Packed and ready for dispatch.');
            unset($this->order);
        } catch (\DomainException $e) {
            session()->flash('order_error', $e->getMessage());
        }
    }

    public function confirmCancel(OrderService $orderService): void
    {
        $order = $this->order;
        if (! $order) {
            return;
        }

        $this->authorize('cancel', $order);

        try {
            $orderService->cancelSellerOrder(
                $order,
                'seller',
                auth()->id(),
                $this->cancelReason ?: 'Cancelled by seller due to inventory constraint'
            );

            session()->flash('order_success', 'Order cancelled and stock restocked.');
            $this->showCancelModal = false;
            unset($this->order);
        } catch (\DomainException $e) {
            session()->flash('order_error', $e->getMessage());
        }
    }
}; ?>

<div class="space-y-6">
    <!-- Breadcrumb & Back -->
    <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
        <a href="{{ route('seller.orders.index') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200" wire:navigate>&larr; Back to Orders</a>
    </div>

    <!-- Alerts -->
    @if (session()->has('order_error'))
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs font-semibold text-red-800 dark:border-red-900/50 dark:bg-red-950/50 dark:text-red-300">
            {{ session('order_error') }}
        </div>
    @endif
    @if (session()->has('order_success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-semibold text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/50 dark:text-emerald-300">
            {{ session('order_success') }}
        </div>
    @endif

    @php
        $sellerOrder = $this->order;
        $feeLookup = app(App\Fees\Contracts\OrderItemFeeSnapshotLookupInterface::class);
        $feeSummary = $sellerOrder ? $feeLookup->getSellerOrderFeeSummary($sellerOrder->id, $sellerOrder->seller_profile_id) : null;
        $itemSnapshots = $sellerOrder ? collect($feeLookup->getSnapshotsForSellerOrder($sellerOrder->id, $sellerOrder->seller_profile_id))->keyBy(fn ($s) => $s->orderItemId) : collect();
    @endphp

    <!-- Header Section with Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Order</span>
            <h1 class="text-xl font-extrabold text-zinc-900 dark:text-white">
                {{ $sellerOrder->order->order_number ?? $sellerOrder->id }}
            </h1>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                Placed on {{ $sellerOrder->placed_at->format('d M Y, h:i A') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-bold uppercase {{ $sellerOrder->status->value === 'cancelled' ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300' : 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300' }}">
                Status: {{ $sellerOrder->status->label() }}
            </span>

            @if ($sellerOrder->status->canConfirm())
                <button
                    type="button"
                    wire:click="confirmOrder"
                    class="rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white shadow-xs hover:bg-amber-600 transition"
                >
                    Confirm Order
                </button>
            @endif

            @if ($sellerOrder->status->canPack())
                <button
                    type="button"
                    wire:click="markPacked"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white shadow-xs hover:bg-indigo-700 transition"
                >
                    Mark as Packed
                </button>
            @endif

            @if ($sellerOrder->status->canCancelBySeller() && ! $this->showCancelModal)
                <button
                    type="button"
                    wire:click="$set('showCancelModal', true)"
                    class="rounded-xl border border-red-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-red-600 hover:bg-red-50 dark:border-red-800 dark:bg-zinc-800 dark:text-red-300 transition"
                >
                    Cancel Order
                </button>
            @endif
        </div>
    </div>

    <!-- Cancellation Prompt Form -->
    @if ($this->showCancelModal)
        <div class="rounded-2xl border border-red-200 bg-red-50/50 p-5 dark:border-red-900/60 dark:bg-red-950/30">
            <h3 class="text-sm font-bold text-red-900 dark:text-red-300">Seller Cancellation</h3>
            <p class="mt-1 text-xs text-red-700 dark:text-red-400">
                Cancelling this order will release the reserved inventory back to live stock.
            </p>
            <div class="mt-3">
                <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Reason for cancellation:</label>
                <input
                    type="text"
                    wire:model="cancelReason"
                    placeholder="e.g. Damaged inventory during inspection"
                    class="w-full sm:w-96 rounded-lg border border-zinc-300 px-3 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                />
            </div>
            <div class="mt-4 flex items-center gap-3">
                <button
                    type="button"
                    wire:click="confirmCancel"
                    class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white shadow-xs hover:bg-red-700 transition"
                >
                    Confirm Cancellation
                </button>
                <button
                    type="button"
                    wire:click="$set('showCancelModal', false)"
                    class="text-xs font-semibold text-zinc-500 hover:text-zinc-800 dark:text-zinc-400"
                >
                    Nevermind
                </button>
            </div>
        </div>
    @endif

    <!-- Content Columns -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Left: Items Table -->
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden">
                <div class="border-b border-zinc-100 bg-zinc-50/50 px-6 py-3.5 text-xs font-bold uppercase tracking-wider text-zinc-600 dark:border-zinc-800 dark:bg-zinc-800/30 dark:text-zinc-400">
                    Order Items ({{ $sellerOrder->items->count() }})
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($sellerOrder->items as $item)
                        <div class="p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl border border-zinc-200 bg-white p-1.5 dark:border-zinc-800 dark:bg-zinc-800">
                                    <img
                                        src="{{ $item->image_path ?: asset('storage/products/placeholder.svg') }}"
                                        alt="{{ $item->title }}"
                                        class="h-full w-full object-contain"
                                    />
                                </div>
                                <div>
                                    <h4 class="font-bold text-zinc-900 dark:text-white text-sm">
                                        {{ $item->title }}
                                    </h4>
                                    @if ($item->variant_title)
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $item->variant_title }}
                                        </p>
                                    @endif
                                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        SKU: <code class="text-zinc-700 dark:text-zinc-300">{{ $item->sku }}</code> &middot; UPID: <code class="text-zinc-700 dark:text-zinc-300">{{ $item->upid }}</code>
                                    </p>
                                    @if ($item->hsn_code)
                                        <p class="text-[11px] text-zinc-400">
                                            HSN: {{ $item->hsn_code }} &middot; GST Rate: {{ $item->gst_rate }}% (Tax: ₹{{ number_format($item->gst_amount / 100) }})
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="text-right sm:text-right">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Qty: <strong>{{ $item->qty }}</strong> &times; ₹{{ number_format($item->unit_price / 100) }}
                                </p>
                                <p class="text-sm font-extrabold text-zinc-900 dark:text-white mt-0.5">
                                    ₹{{ number_format($item->line_total / 100) }}
                                </p>
                            </div>
                        </div>

                        @if ($itemSnapshots->has($item->id))
                            @php $snap = $itemSnapshots->get($item->id); @endphp
                            <div class="px-6 pb-4 pt-1">
                                <div class="rounded-xl border border-zinc-100 bg-zinc-50/75 p-3 text-xs dark:border-zinc-800 dark:bg-zinc-800/40">
                                    <div class="flex items-center justify-between font-semibold">
                                        <span class="text-emerald-700 dark:text-emerald-400">Net Item Payout: {{ $snap->netPayout->format() }}</span>
                                        <span class="text-zinc-500 font-mono text-[11px]">Total Deductions: -{{ $snap->totalDeductions->format() }}</span>
                                    </div>
                                    <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-2 text-[10px] text-zinc-500">
                                        <div>Commission: <span class="font-mono text-red-600">-{{ $snap->commission->format() }}</span></div>
                                        <div>Closing Fee: <span class="font-mono text-red-600">-{{ $snap->closingFee->format() }}</span></div>
                                        <div>Shipping Fee: <span class="font-mono text-red-600">-{{ $snap->shippingFee->format() }}</span></div>
                                        <div>Taxes (GST+TCS+TDS): <span class="font-mono text-red-600">-{{ $snap->claimableCredits->format() }}</span></div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Activity Log -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    Status History & Activity
                </h3>
                <div class="mt-4 space-y-3">
                    @forelse ($sellerOrder->statusHistories as $log)
                        <div class="flex items-start gap-3 text-xs">
                            <span class="mt-1 h-2 w-2 rounded-full bg-amber-500"></span>
                            <div>
                                <span class="font-bold text-zinc-900 dark:text-white">{{ ucfirst($log->to_status) }}</span>
                                <span class="text-zinc-500">by {{ ucfirst($log->changed_by_type) }} on {{ $log->created_at->format('d M Y, h:i A') }}</span>
                                @if ($log->reason)
                                    <p class="mt-0.5 text-zinc-600 dark:text-zinc-400 italic">"{{ $log->reason }}"</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-zinc-400">No activity recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right: Order Value & Delivery Info -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Order Value & Package Deductions Card -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    Package Value & Deductions
                </h3>
                <div class="flex items-baseline justify-between">
                    <span class="text-xs text-zinc-600 dark:text-zinc-400">Gross Package Value</span>
                    <span class="text-lg font-bold text-zinc-900 dark:text-white">
                        ₹{{ number_format($sellerOrder->grand_total / 100, 2) }}
                    </span>
                </div>

                @if ($feeSummary)
                    <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800 space-y-2 text-xs">
                        <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                            <span>Marketplace Commission</span>
                            <span class="font-mono text-red-600 dark:text-red-400">-{{ $feeSummary->commissionTotal->format() }}</span>
                        </div>
                        <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                            <span>Fixed Closing Fees</span>
                            <span class="font-mono text-red-600 dark:text-red-400">-{{ $feeSummary->closingFeeTotal->format() }}</span>
                        </div>
                        <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                            <span>Package Shipping Fee</span>
                            <span class="font-mono text-red-600 dark:text-red-400">-{{ $feeSummary->shippingFeeTotal->format() }}</span>
                        </div>
                        @if ($feeSummary->collectionFeeTotal->isPositive())
                            <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                                <span>Collection Fee</span>
                                <span class="font-mono text-red-600 dark:text-red-400">-{{ $feeSummary->collectionFeeTotal->format() }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                            <span>GST on Fees (18%)</span>
                            <span class="font-mono text-red-600 dark:text-red-400">-{{ $feeSummary->gstOnFeesTotal->format() }}</span>
                        </div>
                        <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                            <span>GST TCS (0.5%)</span>
                            <span class="font-mono text-red-600 dark:text-red-400">-{{ $feeSummary->tcsTotal->format() }}</span>
                        </div>
                        <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                            <span>Income Tax TDS (0.1%)</span>
                            <span class="font-mono text-red-600 dark:text-red-400">-{{ $feeSummary->tdsTotal->format() }}</span>
                        </div>

                        <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800 flex justify-between font-bold text-zinc-900 dark:text-white">
                            <span>Total Deductions</span>
                            <span class="font-mono text-red-600 dark:text-red-400">-{{ $feeSummary->totalDeductions->format() }}</span>
                        </div>

                        <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/60 mt-3">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">Net Bank Payout</span>
                            <div class="text-xl font-extrabold text-emerald-900 dark:text-emerald-200 mt-0.5">
                                {{ $feeSummary->netPayout->format() }}
                            </div>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-3 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900/60">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-blue-800 dark:text-blue-300">Claimable Tax Credits</span>
                            <div class="text-sm font-bold text-blue-900 dark:text-blue-200 mt-0.5">
                                {{ $feeSummary->claimableCredits->format() }}
                            </div>
                            <p class="text-[10px] text-blue-600 dark:text-blue-400 mt-0.5">GST ITC + TCS cash ledger + TDS 26AS</p>
                        </div>
                    </div>
                @else
                    <div class="text-[11px] text-zinc-400 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        All prices are GST-inclusive.
                    </div>
                @endif
            </div>

            <!-- Customer Destination -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    Shipping Destination
                </h3>
                <div class="text-xs text-zinc-700 dark:text-zinc-300 space-y-1">
                    <p class="font-bold text-zinc-900 dark:text-white">{{ $sellerOrder->order->delivery_address['name'] ?? 'Recipient' }}</p>
                    <p class="text-zinc-800 dark:text-zinc-200">
                        Phone: <strong class="text-zinc-900 dark:text-white">{{ $sellerOrder->order->delivery_address['phone'] ?? '' }}</strong>
                        @if (! empty($sellerOrder->order->delivery_address['alternate_phone']))
                            <span class="text-zinc-400"> / {{ $sellerOrder->order->delivery_address['alternate_phone'] }}</span>
                        @endif
                    </p>
                    <p class="text-zinc-600 dark:text-zinc-400 font-medium">{{ $sellerOrder->order->delivery_address['address_line'] ?? ($sellerOrder->order->delivery_address['line'] ?? '') }}</p>
                    @if (! empty($sellerOrder->order->delivery_address['locality']))
                        <p class="text-zinc-500">{{ $sellerOrder->order->delivery_address['locality'] }}</p>
                    @endif
                    @if (! empty($sellerOrder->order->delivery_address['landmark']))
                        <p class="text-zinc-500">Landmark: {{ $sellerOrder->order->delivery_address['landmark'] }}</p>
                    @endif
                    <p class="text-zinc-500">{{ $sellerOrder->order->delivery_address['city'] ?? '' }}, {{ $sellerOrder->order->delivery_state }} - {{ $sellerOrder->order->delivery_address['pincode'] ?? '' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

