<?php

namespace App\Livewire\Admin;

use App\Returns\Enums\CourierClaimStatus;
use App\Returns\Enums\RtoAdminDecision;
use App\Returns\Models\CourierClaim;
use App\Returns\Models\PlatformLoss;
use App\Returns\Models\RtoIntakeReport;
use App\Returns\Models\UndeliveredItem;
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

    #[Computed]
    public function pendingUndeliveredItems()
    {
        return UndeliveredItem::where('status', UndeliveredItem::STATUS_PENDING)->latest()->limit(50)->get();
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-extrabold text-zinc-900 dark:text-white">Returns &amp; Claims</h1>
        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">RTO reports awaiting a decision, open courier claims, platform losses and undelivered items. Act on each from its shipment page.</p>
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
            ['title' => 'Undelivered items (pending phase B)', 'test' => 'undelivered-items', 'rows' => $this->pendingUndeliveredItems->map(fn ($u) => [
                'shipment' => $u->shipment_id,
                'main' => "Seller order {$u->seller_order_id}",
                'detail' => "Order item #{$u->order_item_id} · recorded ".format_datetime($u->created_at),
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
