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

    @php $sellerOrder = $this->order; @endphp

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
            <!-- Order Value Card -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    Order Value
                </h3>
                <div class="flex items-baseline justify-between">
                    <span class="text-xs text-zinc-600 dark:text-zinc-400">Order value</span>
                    <span class="text-xl font-extrabold text-zinc-900 dark:text-white">
                        ₹{{ number_format($sellerOrder->grand_total / 100) }}
                    </span>
                </div>
                <div class="text-[11px] text-zinc-400 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    All prices are GST-inclusive.
                </div>
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

