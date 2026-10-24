<?php

namespace App\Livewire\Admin;

use App\Orders\Models\Order;
use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] #[Title('Orders Management')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $status = 'all';

    public function boot(): void
    {
        Gate::authorize(AdminPermissions::ORDERS_VIEW);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders()
    {
        return Order::with(['user', 'sellerOrders.items'])
            ->when($this->status !== 'all', function (Builder $q) {
                $q->where('status', $this->status);
            })
            ->when(filled($this->search), function (Builder $q) {
                $term = trim($this->search);
                $q->where(function (Builder $sub) use ($term) {
                    $sub->where('order_number', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                });
            })
            ->latest('placed_at')
            ->paginate(15);
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-2xl">
                Orders
            </h1>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                Inspect customer orders, order items audit, and seller fulfillment status.
            </p>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2">
            @php
                $tabs = [
                    'all' => 'All Orders',
                    'placed' => 'Placed',
                    'partially_cancelled' => 'Partially Cancelled',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ];
            @endphp
            @foreach ($tabs as $key => $label)
                <button
                    type="button"
                    wire:click="$set('status', '{{ $key }}')"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $status === $key ? 'bg-amber-500 text-white shadow-xs' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="w-full sm:w-72">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search order #, customer name/email..."
                class="w-full rounded-xl border border-zinc-300 px-3.5 py-2 text-xs shadow-xs dark:border-zinc-700 dark:bg-zinc-900"
            />
        </div>
    </div>

    <!-- Orders Table -->
    <div class="rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden">
        @if ($this->orders->isEmpty())
            <div class="p-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                No orders found.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500 uppercase tracking-wider font-bold dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-400">
                        <tr>
                            <th class="py-3 px-4">Order #</th>
                            <th class="py-3 px-4">Customer</th>
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Packages</th>
                            <th class="py-3 px-4">Grand Total</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->orders as $order)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition">
                                <td class="py-3 px-4 font-bold text-zinc-900 dark:text-white">
                                    {{ $order->order_number }}
                                </td>
                                <td class="py-3 px-4 text-zinc-700 dark:text-zinc-300">
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $order->user->name ?? 'Deleted User' }}</div>
                                    <div class="text-[11px] text-zinc-400">{{ $order->user->email ?? '' }}</div>
                                </td>
                                <td class="py-3 px-4 text-zinc-600 dark:text-zinc-400">
                                    {{ $order->placed_at->format('d M Y, h:i A') }}
                                </td>
                                <td class="py-3 px-4 text-zinc-700 dark:text-zinc-300">
                                    {{ $order->sellerOrders->count() }} {{ $order->sellerOrders->count() === 1 ? 'seller' : 'sellers' }}
                                </td>
                                <td class="py-3 px-4 font-bold text-zinc-900 dark:text-white">
                                    ₹{{ number_format($order->grand_total / 100) }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-bold uppercase {{ $order->status->value === 'cancelled' ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300' : ($order->status->value === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200') }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <a
                                        href="{{ route('admin.orders.show', ['order' => $order->id]) }}"
                                        class="rounded-lg border border-zinc-300 px-3 py-1 font-semibold text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800 transition"
                                        wire:navigate
                                    >
                                        Inspect
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-zinc-100 dark:border-zinc-800">
                {{ $this->orders->links() }}
            </div>
        @endif
    </div>
</div>

