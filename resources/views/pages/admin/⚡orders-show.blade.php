<?php

namespace App\Livewire\Admin;

use App\Orders\Models\Order;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Order Details')] class extends Component {
    public Order $order;

    public function boot(): void
    {
        Gate::authorize(AdminPermissions::ORDERS_VIEW);
    }

    public function mount(Order $order): void
    {
        $this->order = $order->load([
            'user',
            'sellerOrders.items',
            'sellerOrders.statusHistories',
        ]);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.orders.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white transition">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Orders</span>
        </a>
    </div>

    <!-- Header Card -->
    <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-2xl">
                    Order {{ $order->order_number }}
                </h1>
                <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold uppercase {{ $order->status->value === 'cancelled' ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300' : ($order->status->value === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200') }}">
                    {{ $order->status->label() }}
                </span>
            </div>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                Placed on {{ $order->placed_at->format('d M Y, h:i A') }} • Payment: {{ $order->payment_method->label() }} ({{ $order->payment_status->label() }})
            </p>
        </div>

        <div class="text-right">
            <span class="text-xs uppercase font-semibold text-zinc-400">Total Order Value</span>
            <div class="text-2xl font-black text-zinc-900 dark:text-white">
                ₹{{ number_format($order->grand_total / 100) }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main: Seller Packages / Orders -->
        <div class="lg:col-span-2 space-y-6">
            <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                Fulfillment Packages ({{ $order->sellerOrders->count() }})
            </h2>

            @foreach ($order->sellerOrders as $sellerOrder)
                <div class="rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden">
                    <div class="border-b border-zinc-100 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-800/40 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-bold text-zinc-900 dark:text-white">
                                Seller: {{ $sellerOrder->seller_store_name }}
                            </div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                GSTIN: {{ $sellerOrder->seller_gstin ?? 'N/A' }} • State: {{ $sellerOrder->seller_pickup_state ?? 'N/A' }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-bold uppercase {{ $sellerOrder->status->value === 'cancelled' ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300' : ($sellerOrder->status->value === 'delivered' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300') }}">
                                {{ $sellerOrder->status->label() }}
                            </span>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-zinc-100 text-zinc-400 font-bold dark:border-zinc-800">
                                <tr>
                                    <th class="py-2.5 px-4">Item Details</th>
                                    <th class="py-2.5 px-3">HSN</th>
                                    <th class="py-2.5 px-3">GST Rate</th>
                                    <th class="py-2.5 px-3">Unit Price</th>
                                    <th class="py-2.5 px-3">Qty</th>
                                    <th class="py-2.5 px-4 text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($sellerOrder->items as $item)
                                    <tr>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-3">
                                                @if ($item->image_path)
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($item->image_path) }}" alt="" class="size-10 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700" />
                                                @else
                                                    <div class="size-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400">
                                                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="font-bold text-zinc-900 dark:text-white">{{ $item->title }}</div>
                                                    @if ($item->variant_title)
                                                        <div class="text-[11px] text-zinc-500">{{ $item->variant_title }}</div>
                                                    @endif
                                                    <div class="text-[10px] text-zinc-400">SKU: {{ $item->sku }} • UPID: {{ $item->upid }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-zinc-600 dark:text-zinc-400">{{ $item->hsn_code ?? '—' }}</td>
                                        <td class="py-3 px-3 text-zinc-600 dark:text-zinc-400">
                                            {{ $item->gst_rate }}%
                                            <div class="text-[10px] text-zinc-400">(₹{{ number_format($item->gst_amount / 100, 2) }})</div>
                                        </td>
                                        <td class="py-3 px-3 font-semibold text-zinc-900 dark:text-white">₹{{ number_format($item->unit_price / 100) }}</td>
                                        <td class="py-3 px-3 text-zinc-700 dark:text-zinc-300 font-bold">{{ $item->qty }}</td>
                                        <td class="py-3 px-4 text-right font-bold text-zinc-900 dark:text-white">₹{{ number_format($item->line_total / 100) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Package Status Timeline -->
                    @if ($sellerOrder->statusHistories->isNotEmpty())
                        <div class="border-t border-zinc-100 bg-zinc-50/40 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                            <h3 class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 mb-2">Audit Trail</h3>
                            <div class="space-y-2">
                                @foreach ($sellerOrder->statusHistories->sortBy('created_at') as $history)
                                    <div class="flex items-center gap-2 text-[11px]">
                                        <span class="size-1.5 rounded-full bg-zinc-400"></span>
                                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">
                                            {{ $history->from_status ? strtoupper($history->from_status) . ' → ' : '' }}{{ strtoupper($history->to_status) }}
                                        </span>
                                        <span class="text-zinc-400">by {{ $history->changed_by_type }}</span>
                                        @if ($history->reason)
                                            <span class="text-zinc-500 italic">({{ $history->reason }})</span>
                                        @endif
                                        <span class="ml-auto text-zinc-400">{{ $history->created_at->format('d M Y, h:i A') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Sidebar: Customer & Delivery Details -->
        <div class="space-y-6">
            <!-- Customer Card -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-3">Customer Information</h2>
                <div class="space-y-1.5 text-xs">
                    <div class="font-bold text-zinc-900 dark:text-white">{{ $order->user->name ?? 'Deleted Customer' }}</div>
                    <div class="text-zinc-600 dark:text-zinc-400">{{ $order->user->email ?? 'N/A' }}</div>
                    @if ($order->user?->phone)
                        <div class="text-zinc-600 dark:text-zinc-400">{{ $order->user->phone }}</div>
                    @endif
                </div>
            </div>

            <!-- Delivery Address Card -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-3">Delivery Address Snapshot</h2>
                @php $addr = $order->delivery_address; @endphp
                <div class="space-y-1 text-xs text-zinc-700 dark:text-zinc-300">
                    <div class="font-bold text-zinc-900 dark:text-white">{{ $addr['name'] ?? '' }}</div>
                    <div>Phone: <span class="font-semibold">{{ $addr['phone'] ?? '' }}</span></div>
                    <div>{{ $addr['address_line'] ?? '' }}</div>
                    @if (!empty($addr['locality']))
                        <div>Locality: {{ $addr['locality'] }}</div>
                    @endif
                    <div>{{ $addr['city'] ?? '' }}, {{ $addr['state'] ?? '' }} - {{ $addr['pincode'] ?? '' }}</div>
                    @if (!empty($addr['landmark']))
                        <div class="text-zinc-500">Landmark: {{ $addr['landmark'] }}</div>
                    @endif
                </div>
            </div>

            <!-- Price Breakdown -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-3">Payment & Totals</h2>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                        <span>Items MRP</span>
                        <span>₹{{ number_format($order->items_mrp_total / 100) }}</span>
                    </div>
                    <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                        <span>Catalog Discount</span>
                        <span>−₹{{ number_format($order->discount_total / 100) }}</span>
                    </div>
                    <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                        <span>Delivery Fee</span>
                        <span>{{ $order->delivery_fee === 0 ? 'FREE' : '₹' . number_format($order->delivery_fee / 100) }}</span>
                    </div>
                    <div class="border-t border-zinc-100 pt-2 flex justify-between font-bold text-sm text-zinc-900 dark:text-white dark:border-zinc-800">
                        <span>Grand Total</span>
                        <span>₹{{ number_format($order->grand_total / 100) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

