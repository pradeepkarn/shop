<?php

namespace App\Livewire\Admin;

use App\Orders\Models\OrderItem;
use App\Returns\Contracts\UndeliveredItemServiceInterface;
use App\Returns\Enums\CourierClaimStatus;
use App\Returns\Enums\RtoAdminDecision;
use App\Returns\Models\CourierClaim;
use App\Returns\Models\PlatformLoss;
use App\Returns\Models\RtoIntakeReport;
use App\Returns\Models\UndeliveredItem;
use App\Services\AdminActivityLogger;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Returns & Claims')] class extends Component {
    public function mount(): void
    {
        $this->authorize('returns.view');
    }

    #[Computed]
    public function pendingRtoReports()
    {
        return RtoIntakeReport::where('admin_decision', RtoAdminDecision::Pending)->latest('reported_at')->limit(50)->get();
    }

    #[Computed]
    public function openClaims()
    {
        return CourierClaim::whereIn('status', [CourierClaimStatus::Submitted, CourierClaimStatus::Approved])->latest()->limit(50)->get();
    }

    #[Computed]
    public function platformLosses()
    {
        return PlatformLoss::latest()->limit(50)->get();
    }

    /**
     * Undelivered units awaiting a decision, and those out on a reship.
     */
    #[Computed]
    public function undeliveredItems()
    {
        return UndeliveredItem::whereIn('status', [UndeliveredItem::STATUS_PENDING, UndeliveredItem::STATUS_RESHIPPING])
            ->latest()
            ->limit(50)
            ->get();
    }

    public function refundUndeliveredItem(string $undeliveredItemId, UndeliveredItemServiceInterface $queue): void
    {
        $this->authorize('returns.manage');

        try {
            $item = $queue->refund($undeliveredItemId, (int) auth()->id());

            AdminActivityLogger::log('returns.undelivered_refunded', $item, [
                'seller_order_id' => $item->seller_order_id,
                'order_item_id' => $item->order_item_id,
                'units' => $item->units,
                'refund_id' => $item->refund_id,
            ]);

            session()->flash('returns_success', 'Refund recorded for the undelivered units.');
            unset($this->undeliveredItems);
        } catch (\Throwable $e) {
            session()->flash('returns_error', 'Refund failed: '.$e->getMessage());
        }
    }

    public function reshipUndeliveredItem(string $undeliveredItemId, UndeliveredItemServiceInterface $queue): void
    {
        $this->authorize('returns.manage');

        try {
            $item = $queue->reship($undeliveredItemId, (int) auth()->id());

            AdminActivityLogger::log('returns.undelivered_reshipped', $item, [
                'seller_order_id' => $item->seller_order_id,
                'order_item_id' => $item->order_item_id,
                'units' => $item->units,
                'reship_shipment_id' => $item->reship_shipment_id,
            ]);

            session()->flash('returns_success', 'Reship created: stock reserved and the seller charged the reship shipping.');
            unset($this->undeliveredItems);
        } catch (\Throwable $e) {
            session()->flash('returns_error', 'Reship failed: '.$e->getMessage());
        }
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-extrabold text-zinc-900 dark:text-white">Returns &amp; Claims</h1>
        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">RTO reports awaiting a decision, open courier claims, platform losses and undelivered items. Act on RTO reports and claims from their shipment page.</p>
    </div>

    @if (session('returns_success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-semibold text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/50 dark:text-emerald-300">{{ session('returns_success') }}</div>
    @endif
    @if (session('returns_error'))
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs font-semibold text-red-800 dark:border-red-900/50 dark:bg-red-950/50 dark:text-red-300">{{ session('returns_error') }}</div>
    @endif

    {{-- Undelivered items queue: one decision per item (refund or reship) --}}
    <div data-test="undelivered-items" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-3">
        <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
            Undelivered items: refund or reship ({{ $this->undeliveredItems->count() }})
        </h2>

        @php $orderItems = OrderItem::whereIn('id', $this->undeliveredItems->pluck('order_item_id'))->get()->keyBy('id'); @endphp

        @forelse ($this->undeliveredItems as $item)
            <div data-test="undelivered-item-{{ $item->id }}" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 rounded-xl bg-zinc-50 p-3 text-xs dark:bg-zinc-800/60">
                <div>
                    <span class="font-bold text-zinc-900 dark:text-white">{{ $orderItems->get($item->order_item_id)?->title ?? 'Item #'.$item->order_item_id }}</span>
                    <span class="text-zinc-500">&times; {{ $item->units }}</span>
                    @if ($item->reship_attempts > 0 && $item->status === UndeliveredItem::STATUS_PENDING)
                        <span class="ml-1 text-amber-700 dark:text-amber-400">(reship came back)</span>
                    @endif
                    <p class="text-zinc-500 dark:text-zinc-400 font-mono break-all">Seller order {{ $item->seller_order_id }}</p>
                </div>

                @if ($item->status === UndeliveredItem::STATUS_RESHIPPING)
                    <a href="{{ route('admin.shipments.show', ['shipment' => $item->reship_shipment_id]) }}" wire:navigate data-test="reship-in-progress" class="font-bold text-indigo-600 underline dark:text-indigo-400">Reship in progress &rarr;</a>
                @else
                    @can('returns.manage')
                        <div class="flex gap-2">
                            <button type="button" wire:click="refundUndeliveredItem('{{ $item->id }}')" wire:confirm="Refund these undelivered units? The customer is not charged for them and the item leaves the queue." data-test="refund-undelivered-btn" class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 font-bold text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">Refund</button>
                            <button type="button" wire:click="reshipUndeliveredItem('{{ $item->id }}')" wire:confirm="Reship these units? Stock is taken from the original listing and the seller is charged the reship shipping." data-test="reship-undelivered-btn" class="rounded-lg bg-indigo-600 px-3 py-1.5 font-bold text-white hover:bg-indigo-700">Reship</button>
                        </div>
                    @endcan
                @endif
            </div>
        @empty
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Nothing here.</p>
        @endforelse
    </div>

    @php
        $sections = [
            ['title' => 'RTO reports awaiting decision', 'test' => 'pending-rto-reports', 'rows' => $this->pendingRtoReports->map(fn ($r) => [
                'shipment' => $r->shipment_id,
                'main' => "Seller order {$r->seller_order_id}",
                'detail' => 'Condition: '.$r->condition_status->value.' · reported '.format_datetime($r->reported_at),
            ])],
            ['title' => 'Open courier claims', 'test' => 'open-claims', 'rows' => $this->openClaims->map(fn ($c) => [
                'shipment' => $c->shipment_id,
                'main' => "{$c->claim_number} · ".strtoupper($c->carrier_name),
                'detail' => $c->claim_type->value.' · ₹'.number_format($c->claim_amount_paise / 100).' · '.$c->status->label(),
            ])],
            ['title' => 'Platform losses', 'test' => 'platform-losses', 'rows' => $this->platformLosses->map(fn ($l) => [
                'shipment' => $l->shipment_id,
                'main' => "Seller order {$l->seller_order_id}",
                'detail' => $l->loss_type.' · loss ₹'.number_format($l->loss_amount_paise / 100).' · declared ₹'.number_format($l->declared_value_paise / 100),
            ])],
        ];
    @endphp

    @foreach ($sections as $section)
        <div data-test="{{ $section['test'] }}" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                {{ $section['title'] }} ({{ $section['rows']->count() }})
            </h2>

            @forelse ($section['rows'] as $row)
                <a href="{{ route('admin.shipments.show', ['shipment' => $row['shipment']]) }}" wire:navigate class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 rounded-xl bg-zinc-50 p-3 text-xs hover:bg-zinc-100 dark:bg-zinc-800/60 dark:hover:bg-zinc-800">
                    <span class="font-bold text-zinc-900 dark:text-white font-mono break-all">{{ $row['main'] }}</span>
                    <span class="text-zinc-500 dark:text-zinc-400">{{ $row['detail'] }}</span>
                </a>
            @empty
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Nothing here.</p>
            @endforelse
        </div>
    @endforeach
</div>
