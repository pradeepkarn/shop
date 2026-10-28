<?php

namespace App\Livewire\Admin;

use App\Orders\Models\OrderItem;
use App\Returns\Contracts\ReturnRequestServiceInterface;
use App\Returns\Contracts\UndeliveredItemServiceInterface;
use App\Returns\Enums\ReturnRequestStatus;
use App\Returns\Models\ReturnRequest;
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

    /** @var array<string, string> request id => rejection reason being typed */
    public array $rejectReason = [];

    /** @var array<string, string> request id => information request being typed */
    public array $infoMessage = [];

    /**
     * Open customer return requests, oldest first.
     */
    #[Computed]
    public function returnRequests()
    {
        return ReturnRequest::with('items')
            ->whereIn('status', ReturnRequestStatus::activeValues())
            ->oldest()
            ->limit(50)
            ->get();
    }

    public function approveReturn(string $returnRequestId, ReturnRequestServiceInterface $returns): void
    {
        $this->authorize('returns.manage');

        try {
            $request = $returns->approve($returnRequestId, (int) auth()->id());
            AdminActivityLogger::log('returns.request_approved', $request, [
                'seller_order_id' => $request->seller_order_id,
                'reason' => $request->reason->value,
                'reverse_shipment_id' => $request->reverse_shipment_id,
            ]);

            $pickup = $request->reverse_shipment_id ? \App\Shipping\Models\Shipment::find($request->reverse_shipment_id) : null;
            match (true) {
                $pickup === null => session()->flash('returns_success', 'Missing-item claim approved: the units are in the refund/reship queue below.'),
                $pickup->isBooked() => session()->flash('returns_success', "Return approved; pickup booked (AWB {$pickup->awb})."),
                default => session()->flash('returns_error', 'Return approved but the pickup booking failed: '.$pickup->booking_error.' Retry or cancel it from the shipment page.'),
            };
            unset($this->returnRequests, $this->undeliveredItems);
        } catch (\Throwable $e) {
            session()->flash('returns_error', 'Approval failed: '.$e->getMessage());
        }
    }

    public function rejectReturn(string $returnRequestId, ReturnRequestServiceInterface $returns): void
    {
        $this->authorize('returns.manage');

        try {
            $request = $returns->reject($returnRequestId, (string) ($this->rejectReason[$returnRequestId] ?? ''), (int) auth()->id());
            AdminActivityLogger::log('returns.request_rejected', $request, ['reason' => $request->rejection_reason]);

            unset($this->rejectReason[$returnRequestId], $this->returnRequests);
            session()->flash('returns_success', 'Return rejected; the customer will see your reason.');
        } catch (\Throwable $e) {
            session()->flash('returns_error', 'Rejection failed: '.$e->getMessage());
        }
    }

    public function requestReturnInfo(string $returnRequestId, ReturnRequestServiceInterface $returns): void
    {
        $this->authorize('returns.manage');

        try {
            $request = $returns->requestInfo($returnRequestId, (string) ($this->infoMessage[$returnRequestId] ?? ''), (int) auth()->id());
            AdminActivityLogger::log('returns.info_requested', $request, ['message' => $request->info_request_message]);

            unset($this->infoMessage[$returnRequestId], $this->returnRequests);
            session()->flash('returns_success', 'Asked the customer for more information.');
        } catch (\Throwable $e) {
            session()->flash('returns_error', 'Could not request information: '.$e->getMessage());
        }
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

            $booking = \App\Shipping\Models\Shipment::find($item->reship_shipment_id);
            $booking?->isBooked()
                ? session()->flash('returns_success', 'Reship created: stock reserved and the seller charged the reship shipping.')
                : session()->flash('returns_error', 'Reship created but the courier booking failed: '.($booking?->booking_error ?? 'unknown error').' Retry or cancel it from the shipment page.');
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

    {{-- Customer return requests --}}
    <div data-test="return-requests" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-3">
        <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
            Return requests ({{ $this->returnRequests->count() }})
        </h2>

        @php $requestItems = OrderItem::whereIn('id', $this->returnRequests->flatMap->items->pluck('order_item_id'))->get()->keyBy('id'); @endphp

        @forelse ($this->returnRequests as $request)
            <div data-test="return-request-{{ $request->id }}" class="rounded-xl bg-zinc-50 p-4 text-xs dark:bg-zinc-800/60 space-y-2">
                <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                    <div>
                        <span class="font-bold text-zinc-900 dark:text-white">{{ $request->reason->label() }}</span>
                        <span class="text-zinc-500">&middot; wants a {{ $request->preference }}</span>
                        <p class="text-zinc-600 dark:text-zinc-300">
                            @foreach ($request->items as $line)
                                {{ $requestItems->get($line->order_item_id)?->title ?? 'Item #'.$line->order_item_id }} &times; {{ $line->units }}@if (! $loop->last), @endif
                            @endforeach
                        </p>
                        @if ($request->customer_notes)<p class="text-zinc-500">“{{ $request->customer_notes }}”</p>@endif
                        @if ($request->customer_response)<p class="text-zinc-500">Customer replied: “{{ $request->customer_response }}”</p>@endif
                        @if ($request->window_override_by_admin_id)<p class="font-semibold text-amber-700">Created after the return window (admin override)</p>@endif
                    </div>
                    <div class="text-right">
                        <span class="rounded-lg bg-zinc-200 px-2 py-0.5 font-bold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200" data-test="return-status">{{ $request->status->label() }}</span>
                        <p class="mt-1 text-zinc-500">{{ count($request->photos ?? []) }} photo(s)</p>
                        @if ($request->reverse_shipment_id)
                            <a href="{{ route('admin.shipments.show', ['shipment' => $request->reverse_shipment_id]) }}" wire:navigate class="font-bold text-indigo-600 underline dark:text-indigo-400">Return pickup &rarr;</a>
                        @endif
                    </div>
                </div>

                @can('returns.manage')
                    @if (in_array($request->status, [ReturnRequestStatus::Requested, ReturnRequestStatus::InfoRequested], true))
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2 border-t border-zinc-200 pt-2 dark:border-zinc-700">
                            <button type="button" wire:click="approveReturn('{{ $request->id }}')" wire:confirm="Approve this return? {{ $request->reason->needsPickup() ? 'A pickup will be booked from the customer.' : 'The seller is debited for the missing units and they move to the refund/reship queue.' }}" data-test="approve-return-btn" class="rounded-lg bg-emerald-600 px-3 py-1.5 font-bold text-white hover:bg-emerald-700">Approve</button>
                            <div class="flex gap-1">
                                <input wire:model="rejectReason.{{ $request->id }}" type="text" placeholder="Reason for rejection" class="flex-1 rounded-lg border border-zinc-300 bg-white p-1.5 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                                <button type="button" wire:click="rejectReturn('{{ $request->id }}')" wire:confirm="Reject this return? The customer will see your reason." data-test="reject-return-btn" class="rounded-lg border border-red-300 px-3 py-1.5 font-bold text-red-600">Reject</button>
                            </div>
                            @if ($request->status === ReturnRequestStatus::Requested)
                                <div class="flex gap-1">
                                    <input wire:model="infoMessage.{{ $request->id }}" type="text" placeholder="What do you need to know?" class="flex-1 rounded-lg border border-zinc-300 bg-white p-1.5 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                                    <button type="button" wire:click="requestReturnInfo('{{ $request->id }}')" data-test="request-info-btn" class="rounded-lg border border-zinc-300 px-3 py-1.5 font-bold text-zinc-700 dark:text-zinc-300">Ask</button>
                                </div>
                            @endif
                        </div>
                    @endif
                @endcan
            </div>
        @empty
            <p class="text-xs text-zinc-500 dark:text-zinc-400">No open return requests.</p>
        @endforelse
    </div>

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
                    @if ($item->origin === UndeliveredItem::ORIGIN_MISSING_ITEM_CLAIM)
                        <span data-test="missing-item-claim" class="ml-1 font-semibold text-indigo-700 dark:text-indigo-300">(missing-item claim; customer paid)</span>
                    @endif
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
