<?php

namespace App\Livewire\Admin;

use App\Orders\Models\SellerOrder;
use App\Returns\Contracts\CourierClaimServiceInterface;
use App\Returns\Contracts\ReturnsLookupInterface;
use App\Returns\Contracts\RtoIntakeServiceInterface;
use App\Services\AdminActivityLogger;
use App\Settlements\Contracts\SettlementServiceInterface;
use App\Shipping\Contracts\ShipmentServiceInterface;
use App\Shipping\Enums\ShipmentStatus;
use App\Shipping\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Shipment Details')] class extends Component {
    public string $shipmentId;

    // Simulation fields
    public string $simulationReason = 'Customer address incomplete / customer unavailable';

    // NDR fields
    public string $ndrAction = 'reattempt';
    public string $ndrPhone = '';
    public string $ndrAddress = '';
    public string $ndrDate = '';

    // RTO decision fields
    public string $rtoNotes = '';

    // Partial delivery fields
    public array $selectedDeliveredItems = [];
    public string $partialNotes = '';

    public function mount(string $shipment): void
    {
        $this->authorize('shipping.view');
        $this->shipmentId = $shipment;

        if (! $this->shipment) {
            abort(404, 'Shipment not found.');
        }

        if ($this->sellerOrder) {
            $this->selectedDeliveredItems = $this->sellerOrder->items->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
    }

    #[Computed]
    public function shipment(): ?Shipment
    {
        return Shipment::with('events')->find($this->shipmentId);
    }

    #[Computed]
    public function sellerOrder(): ?SellerOrder
    {
        return $this->shipment ? SellerOrder::with('items')->find($this->shipment->seller_order_id) : null;
    }

    public function pollTracking(?ShipmentServiceInterface $shipmentService = null): void
    {
        $this->authorize('shipping.manage');
        $shipmentService = $shipmentService ?: app(ShipmentServiceInterface::class);

        $shipmentModel = $this->shipment;
        if (! $shipmentModel) {
            return;
        }

        try {
            $shipmentService->syncTracking($shipmentModel);
            session()->flash('shipment_success', 'Tracking successfully synced with carrier.');
            unset($this->shipment);
        } catch (\Throwable $e) {
            session()->flash('shipment_error', 'Tracking sync failed: '.$e->getMessage());
        }
    }

    public function cancelShipment(?ShipmentServiceInterface $shipmentService = null): void
    {
        $this->authorize('shipping.manage');
        $shipmentService = $shipmentService ?: app(ShipmentServiceInterface::class);

        $shipmentModel = $this->shipment;
        if (! $shipmentModel) {
            return;
        }

        try {
            $shipmentService->cancelShipment(
                shipmentId: $shipmentModel->id,
                actorType: 'admin',
                actorId: auth()->id(),
                reason: 'Shipment cancelled by administration',
            );

            AdminActivityLogger::log('shipping.cancelled', $shipmentModel);
            session()->flash('shipment_success', 'Shipment cancelled and seller order reverted to Packed.');
            unset($this->shipment);
        } catch (\Throwable $e) {
            session()->flash('shipment_error', 'Cancellation failed: '.$e->getMessage());
        }
    }

    public function simulateTransition(string $targetStatus, ?ShipmentServiceInterface $shipmentService = null): void
    {
        $this->authorize('shipping.manage');
        $shipmentService = $shipmentService ?: app(ShipmentServiceInterface::class);

        $shipmentModel = $this->shipment;
        if (! $shipmentModel) {
            return;
        }

        try {
            $statusEnum = ShipmentStatus::from($targetStatus);
            $shipmentService->simulateStatusTransition(
                $shipmentModel->id,
                $statusEnum,
                ['reason' => $this->simulationReason]
            );

            AdminActivityLogger::log('shipping.simulated_transition', $shipmentModel, [
                'target_status' => $targetStatus,
            ]);

            session()->flash('shipment_success', "Simulated status transition to [{$statusEnum->label()}] successfully.");
            unset($this->shipment);
        } catch (\Throwable $e) {
            session()->flash('shipment_error', 'Simulation failed: '.$e->getMessage());
        }
    }

    public function submitNdr(?ShipmentServiceInterface $shipmentService = null): void
    {
        $this->authorize('shipping.manage');
        $shipmentService = $shipmentService ?: app(ShipmentServiceInterface::class);

        $shipmentModel = $this->shipment;
        if (! $shipmentModel) {
            return;
        }

        try {
            $result = $shipmentService->actionNdr($shipmentModel->id, $this->ndrAction, [
                'phone' => $this->ndrPhone,
                'address' => $this->ndrAddress,
                'deferred_date' => $this->ndrDate,
            ]);

            AdminActivityLogger::log('shipping.ndr_actioned', $shipmentModel, [
                'action' => $this->ndrAction,
            ]);

            if ($result['success']) {
                session()->flash('shipment_success', $result['message']);
            } else {
                session()->flash('shipment_error', $result['message']);
            }

            unset($this->shipment);
        } catch (\Throwable $e) {
            session()->flash('shipment_error', 'NDR action failed: '.$e->getMessage());
        }
    }

    public function submitRtoDecision(string $decision, ?RtoIntakeServiceInterface $rtoIntakeService = null): void
    {
        $this->authorize('returns.manage');
        $rtoIntakeService = $rtoIntakeService ?: app(RtoIntakeServiceInterface::class);

        $shipmentModel = $this->shipment;
        if (! $shipmentModel) {
            return;
        }

        try {
            $rtoIntakeService->decideRtoReport(
                $shipmentModel->seller_order_id,
                $decision,
                auth()->id(),
                $this->rtoNotes
            );

            AdminActivityLogger::log('returns.rto_decided', $shipmentModel, [
                'decision' => $decision,
            ]);

            session()->flash('shipment_success', "RTO decision recorded: [{$decision}].");
            unset($this->shipment);
        } catch (\Throwable $e) {
            session()->flash('shipment_error', 'RTO decision failed: '.$e->getMessage());
        }
    }

    public function confirmLostShipment(
        ?SettlementServiceInterface $settlementService = null,
        ?CourierClaimServiceInterface $claimService = null
    ): void {
        $this->authorize('shipping.manage');
        $settlementService = $settlementService ?: app(SettlementServiceInterface::class);
        $claimService = $claimService ?: app(CourierClaimServiceInterface::class);

        $shipmentModel = $this->shipment;
        if (! $shipmentModel) {
            return;
        }

        try {
            // Courier claim is raised for the declared value snapshotted when the shipment was created
            if ($shipmentModel->declared_value_paise === null) {
                throw new \DomainException('No declared value was recorded for this shipment, so the courier claim amount is unknown.');
            }

            DB::transaction(function () use ($settlementService, $claimService, $shipmentModel) {
                $settlementService->confirmLostShipmentSettlement($shipmentModel->seller_order_id, auth()->id());
                $claimService->recordClaim(
                    shipmentId: $shipmentModel->id,
                    sellerOrderId: $shipmentModel->seller_order_id,
                    carrierName: $shipmentModel->provider,
                    claimType: 'lost_shipment',
                    claimAmountPaise: $shipmentModel->declared_value_paise,
                    bearer: 'platform',
                    adminId: auth()->id(),
                    notes: 'Shipment confirmed lost by administration (Platform bears loss)',
                );
            });

            AdminActivityLogger::log('shipping.lost_confirmed', $shipmentModel);
            session()->flash('shipment_success', 'Shipment confirmed lost. Seller settlement continues as delivered, courier claim recorded against platform.');
            unset($this->shipment);
        } catch (\Throwable $e) {
            session()->flash('shipment_error', 'Lost shipment confirmation failed: '.$e->getMessage());
        }
    }

    public function submitPartialDelivery(
        ?ShipmentServiceInterface $shipmentService = null,
        ?SettlementServiceInterface $settlementService = null
    ): void {
        $this->authorize('shipping.manage');
        $shipmentService = $shipmentService ?: app(ShipmentServiceInterface::class);
        $settlementService = $settlementService ?: app(SettlementServiceInterface::class);

        $shipmentModel = $this->shipment;
        if (! $shipmentModel || ! $this->sellerOrder) {
            return;
        }

        try {
            $allItemIds = $this->sellerOrder->items->pluck('id')->map(fn ($id) => (int) $id)->all();
            $delivered = array_map('intval', $this->selectedDeliveredItems);
            $undelivered = array_values(array_diff($allItemIds, $delivered));

            // Resolution and hold release commit together, or neither does
            DB::transaction(function () use ($shipmentService, $settlementService, $shipmentModel, $delivered, $undelivered) {
                $shipmentService->resolvePartialDelivery(
                    $shipmentModel->id,
                    $delivered,
                    $undelivered,
                    auth()->id(),
                    $this->partialNotes
                );

                $settlementService->releasePartialDeliveryHold($shipmentModel->seller_order_id);
            });

            AdminActivityLogger::log('shipping.partial_delivery_marked', $shipmentModel, [
                'delivered' => $delivered,
                'undelivered' => $undelivered,
            ]);

            session()->flash('shipment_success', 'Partial delivery marked and settlement hold released.');
            unset($this->shipment);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('shipment_error', 'Could not confirm delivered items. Nothing was changed and the settlement hold is still in place. Reason: '.$e->getMessage());
        }
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
        <a href="{{ route('admin.shipments.index') }}" wire:navigate class="hover:text-zinc-800 dark:hover:text-zinc-200">&larr; Back to Shipments</a>
    </div>

    @if (session()->has('shipment_success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-semibold text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/50 dark:text-emerald-300">
            {{ session('shipment_success') }}
        </div>
    @endif
    @if (session()->has('shipment_error'))
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs font-semibold text-red-800 dark:border-red-900/50 dark:bg-red-950/50 dark:text-red-300">
            {{ session('shipment_error') }}
        </div>
    @endif

    @php
        $shipmentModel = $this->shipment;
        $returnsLookup = app(\App\Returns\Contracts\ReturnsLookupInterface::class);
        $rtoReport = $returnsLookup->getRtoReportForShipment($shipmentModel->id);
        $courierClaims = $returnsLookup->getClaimsForShipment($shipmentModel->id);
        $orderModel = $this->sellerOrder;
        $isFakeSimulation = $shipmentModel->provider === 'fake' && (bool) config('shipping.fake_simulation_enabled', false);
    @endphp

    {{-- Fake Driver Simulation Toolbar (Admin only, fake carrier only, enabled only via config) --}}
    @if ($isFakeSimulation)
        <div data-test="fake-simulation-toolbar" class="rounded-2xl border-2 border-dashed border-amber-300 bg-amber-50/50 p-5 dark:border-amber-700/60 dark:bg-amber-950/20 space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="inline-flex size-2 rounded-full bg-amber-500 animate-ping"></span>
                    <h3 class="text-xs font-black uppercase tracking-wider text-amber-900 dark:text-amber-200">
                        Carrier Simulation Toolbar (Fake Driver)
                    </h3>
                </div>
                <span class="text-[11px] font-mono text-amber-700 dark:text-amber-300">Local Test Environment</span>
            </div>

            <p class="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                Advance this fake shipment through lifecycle milestones. Each transition processes identically to live webhooks.
            </p>

            <div class="flex flex-wrap gap-2 pt-1">
                @php
                    $simStatuses = [
                        'picked_up' => 'Picked Up',
                        'in_transit' => 'In Transit',
                        'out_for_delivery' => 'Out for Delivery',
                        'delivered' => 'Delivered',
                        'delivery_failed' => 'Delivery Failed (NDR)',
                        'rto_initiated' => 'RTO Initiated',
                        'rto_delivered' => 'RTO Delivered',
                        'lost' => 'Lost Shipment',
                        'exception' => 'Exception',
                        'partial_delivered' => 'Partial Delivered',
                    ];
                @endphp

                @foreach ($simStatuses as $statusKey => $statusLabel)
                    <button
                        type="button"
                        wire:click="simulateTransition('{{ $statusKey }}')"
                        wire:loading.attr="disabled"
                        data-test="simulate-{{ $statusKey }}"
                        class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-bold text-amber-900 shadow-2xs hover:bg-amber-100 hover:border-amber-400 dark:border-amber-800 dark:bg-zinc-900 dark:text-amber-200 dark:hover:bg-amber-950/60 transition"
                    >
                        &rarr; {{ $statusLabel }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Shipment</span>
            <h1 class="text-xl font-extrabold text-zinc-900 dark:text-white font-mono">
                {{ $shipmentModel->awb ?: $shipmentModel->id }}
            </h1>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                Created on @datetime($shipmentModel->created_at) &bull; Provider: <strong class="uppercase text-zinc-700 dark:text-zinc-300">{{ $shipmentModel->provider }}</strong>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-bold {{ $shipmentModel->status->badgeClasses() }}" data-test="shipment-status-badge">
                Status: {{ $shipmentModel->status->label() }}
            </span>

            @if ($shipmentModel->label_path)
                <a
                    href="{{ route('shipping.shipments.label', ['shipment' => $shipmentModel->id]) }}"
                    target="_blank"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-emerald-700 transition"
                >
                    Download Label PDF
                </a>
            @endif

            @if ($shipmentModel->public_tracking_url)
                <a
                    href="{{ $shipmentModel->public_tracking_url }}"
                    target="_blank"
                    class="rounded-xl border border-zinc-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 transition"
                >
                    Public Tracking &rarr;
                </a>
            @endif

            @can('shipping.manage')
                @if (! $shipmentModel->status->isTerminal())
                    <button
                        type="button"
                        wire:click="pollTracking"
                        wire:loading.attr="disabled"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-700 transition"
                    >
                        Sync Tracking
                    </button>

                    <button
                        type="button"
                        wire:click="cancelShipment"
                        wire:confirm="Cancel this shipment? The order will be reverted to Packed."
                        class="rounded-xl border border-red-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-red-600 hover:bg-red-50 dark:border-red-800 dark:bg-zinc-800 dark:text-red-300 transition"
                    >
                        Cancel Shipment
                    </button>
                @endif
            @endcan
        </div>
    </div>

    {{-- NDR / Delivery Failed Action Card --}}
    @if ($shipmentModel->ndr_attempt_count > 0 || $shipmentModel->status === ShipmentStatus::DeliveryFailed)
        <div data-test="ndr-management-card" class="rounded-2xl border border-amber-200 bg-amber-50/70 p-6 shadow-xs dark:border-amber-900/50 dark:bg-amber-950/30 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                        Non-Delivery Report (NDR) Management &mdash; Attempt #{{ $shipmentModel->ndr_attempt_count }}
                    </h3>
                    <p class="text-xs text-amber-800 dark:text-amber-300 mt-0.5">
                        Carrier reported: <strong class="font-semibold">{{ $shipmentModel->latest_ndr_reason ?: 'Customer unavailable / delivery failure' }}</strong>
                    </p>
                </div>
                @if ($shipmentModel->ndr_action)
                    <span class="rounded-lg bg-amber-200/80 px-2.5 py-1 text-xs font-bold text-amber-900 dark:bg-amber-900/60 dark:text-amber-200">
                        Actioned: {{ strtoupper($shipmentModel->ndr_action) }} (@datetime($shipmentModel->ndr_actioned_at))
                    </span>
                @endif
            </div>

            @can('shipping.manage')
                <div class="pt-3 border-t border-amber-200/60 dark:border-amber-900/40">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div>
                            <label class="block text-xs font-bold text-amber-900 dark:text-amber-200">Action</label>
                            <select wire:model="ndrAction" class="mt-1 w-full rounded-lg border border-amber-300 bg-white p-2 text-xs text-zinc-900 dark:border-amber-800 dark:bg-zinc-900 dark:text-white">
                                <option value="reattempt">Re-Attempt Delivery</option>
                                <option value="rto">Return To Origin (RTO)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-amber-900 dark:text-amber-200">Update Phone (Optional)</label>
                            <input wire:model="ndrPhone" type="text" placeholder="10-digit phone" class="mt-1 w-full rounded-lg border border-amber-300 bg-white p-2 text-xs text-zinc-900 dark:border-amber-800 dark:bg-zinc-900 dark:text-white" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-amber-900 dark:text-amber-200">Updated Instructions / Address</label>
                            <input wire:model="ndrAddress" type="text" placeholder="Delivery instructions" class="mt-1 w-full rounded-lg border border-amber-300 bg-white p-2 text-xs text-zinc-900 dark:border-amber-800 dark:bg-zinc-900 dark:text-white" />
                        </div>

                        <div>
                            <button
                                type="button"
                                wire:click="submitNdr"
                                data-test="submit-ndr-action-btn"
                                class="w-full rounded-lg bg-amber-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-amber-700 transition shadow-xs"
                            >
                                Submit Carrier NDR Action
                            </button>
                        </div>
                    </div>
                </div>
            @endcan
        </div>
    @endif

    {{-- Lost Shipment Card --}}
    @if ($shipmentModel->status === ShipmentStatus::Lost)
        <div data-test="lost-shipment-card" class="rounded-2xl border border-red-200 bg-red-50/70 p-6 shadow-xs dark:border-red-900/50 dark:bg-red-950/30 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-red-900 dark:text-red-200">
                        Shipment Declared Lost
                    </h3>
                    <p class="text-xs text-red-800 dark:text-red-300 mt-0.5">
                        Per marketplace policy, <strong>Platform bears courier loss</strong>. The seller's settlement will continue as delivered (normal net payout).
                    </p>
                </div>

                @can('shipping.manage')
                    <button
                        type="button"
                        wire:click="confirmLostShipment"
                        data-test="confirm-lost-shipment-btn"
                        class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-red-700 transition shadow-xs"
                    >
                        Confirm Loss & File Platform Claim
                    </button>
                @endcan
            </div>
        </div>
    @endif

    {{-- Partial Delivery Item Review Card --}}
    @if ($shipmentModel->status === ShipmentStatus::PartialDelivered && $orderModel && empty($shipmentModel->partial_delivery_data['resolved_at']))
        <div data-test="partial-delivery-card" class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-6 shadow-xs dark:border-indigo-900/50 dark:bg-indigo-950/30 space-y-4">
            <div>
                <h3 class="text-sm font-bold text-indigo-900 dark:text-indigo-200">
                    Partial Delivery Review & Settlement Hold Release
                </h3>
                <p class="text-xs text-indigo-800 dark:text-indigo-300 mt-0.5">
                    Select the package items successfully delivered to customer. Undelivered items will be marked as returned.
                </p>
            </div>

            <div class="space-y-2">
                @foreach ($orderModel->items as $item)
                    <label class="flex items-center gap-3 rounded-xl border border-indigo-200/80 bg-white p-3 text-xs dark:border-zinc-800 dark:bg-zinc-900 cursor-pointer">
                        <input
                            type="checkbox"
                            value="{{ $item->id }}"
                            wire:model="selectedDeliveredItems"
                            class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="font-medium text-zinc-900 dark:text-white flex-1">
                            {{ $item->product_name }} &times; {{ $item->qty }} (₹{{ number_format($item->unit_price / 100) }} each)
                        </span>
                        <span class="text-zinc-500 font-mono text-[11px]">Item #{{ $item->id }}</span>
                    </label>
                @endforeach
            </div>

            <div class="flex items-center gap-4">
                <input wire:model="partialNotes" type="text" placeholder="Admin verification notes" class="flex-1 rounded-lg border border-indigo-300 bg-white p-2 text-xs text-zinc-900 dark:border-indigo-800 dark:bg-zinc-900 dark:text-white" />
                @can('shipping.manage')
                    <button
                        type="button"
                        wire:click="submitPartialDelivery"
                        data-test="confirm-partial-delivery-btn"
                        class="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-indigo-700 transition shadow-xs"
                    >
                        Confirm Delivered Items & Release Hold
                    </button>
                @endcan
            </div>
        </div>
    @endif

    {{-- RTO Review & Admin Decision Card --}}
    @if ($shipmentModel->status === ShipmentStatus::RtoDelivered || $rtoReport)
        <div data-test="rto-review-card" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-100 pb-3 dark:border-zinc-800">
                <div>
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white">
                        Return to Origin (RTO) Intake Review
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Inspection and restocking decisions for returned package.
                    </p>
                </div>

                @if ($rtoReport)
                    <span class="rounded-lg px-2.5 py-1 text-xs font-bold {{ $rtoReport->adminDecision === \App\Returns\Enums\RtoAdminDecision::Restocked ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($rtoReport->adminDecision === \App\Returns\Enums\RtoAdminDecision::WrittenOff ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300') }}" data-test="rto-decision-badge">
                        Decision: {{ strtoupper($rtoReport->adminDecision->value) }}
                    </span>
                @else
                    @php
                        $isDeadlinePassed = $returnsLookup->isRtoInspectionDeadlinePassed($orderModel?->rto_delivered_at?->toIso8601String());
                    @endphp
                    @if ($isDeadlinePassed)
                        <span class="rounded-lg bg-red-100 px-2.5 py-1 text-xs font-bold text-red-800 dark:bg-red-950 dark:text-red-300" data-test="rto-missed-deadline-badge">
                            Deadline Passed (Seller did not report)
                        </span>
                    @else
                        <span class="rounded-lg bg-sky-100 px-2.5 py-1 text-xs font-bold text-sky-800 dark:bg-sky-950 dark:text-sky-300">
                            Awaiting Seller Inspection
                        </span>
                    @endif
                @endif
            </div>

            @if ($rtoReport)
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-zinc-400">Condition Reported:</span>
                        <p class="font-bold text-zinc-900 dark:text-white mt-1">{{ strtoupper($rtoReport->conditionStatus->value) }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-400">Reported At:</span>
                        <p class="font-bold text-zinc-900 dark:text-white mt-1">@datetime($rtoReport->reportedAt)</p>
                    </div>
                    <div>
                        <span class="text-zinc-400">Seller Notes:</span>
                        <p class="text-zinc-700 dark:text-zinc-300 mt-1">{{ $rtoReport->notes ?: 'None provided' }}</p>
                    </div>
                </div>

                @if (! empty($rtoReport->photos))
                    <div class="pt-2">
                        <span class="text-xs font-bold text-zinc-400">Inspection Photos ({{ count($rtoReport->photos) }})</span>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($rtoReport->photos as $photo)
                                <a href="{{ Storage::disk('public')->url($photo) }}" target="_blank" class="block size-16 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700">
                                    <img src="{{ Storage::disk('public')->url($photo) }}" alt="RTO Photo" class="size-full object-cover" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif

            @can('returns.manage')
                @if (! $rtoReport || $rtoReport->adminDecision === \App\Returns\Enums\RtoAdminDecision::Pending)
                    <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 space-y-3">
                        <input wire:model="rtoNotes" type="text" placeholder="Admin decision notes" class="w-full rounded-lg border border-zinc-300 bg-white p-2 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />

                        <div class="flex flex-wrap gap-3">
                            <button
                                type="button"
                                wire:click="submitRtoDecision('restocked')"
                                data-test="rto-restock-btn"
                                class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-emerald-700 transition shadow-xs"
                            >
                                Restock Inventory & Refresh Buy Box
                            </button>

                            <button
                                type="button"
                                wire:click="submitRtoDecision('written_off')"
                                data-test="rto-writeoff-btn"
                                class="rounded-xl border border-red-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-red-600 hover:bg-red-50 dark:border-red-800 dark:bg-zinc-800 dark:text-red-300 transition"
                            >
                                Write Off (Damaged / Unsellable)
                            </button>
                        </div>
                    </div>
                @endif
            @endcan
        </div>
    @endif

    <!-- Details Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Specifications & Timeline -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Package Specs Card -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    Package Dimensions & Weights
                </h3>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="text-zinc-400 uppercase text-[10px] font-bold">Actual Weight</span>
                        <p class="font-bold text-zinc-900 dark:text-white mt-1">{{ number_format($shipmentModel->actual_weight_grams) }} g</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase text-[10px] font-bold">Volumetric Weight</span>
                        <p class="font-bold text-zinc-900 dark:text-white mt-1">{{ number_format($shipmentModel->volumetric_weight_grams) }} g</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase text-[10px] font-bold">Chargeable Weight</span>
                        <p class="font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($shipmentModel->chargeable_weight_grams) }} g</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase text-[10px] font-bold">Dimensions (L&times;W&times;H)</span>
                        <p class="font-bold text-zinc-900 dark:text-white mt-1">{{ $shipmentModel->length_cm }} &times; {{ $shipmentModel->width_cm }} &times; {{ $shipmentModel->height_cm }} cm</p>
                    </div>
                </div>
            </div>

            <!-- Tracking Timeline -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    Tracking Events History ({{ $shipmentModel->events->count() }})
                </h3>

                @if ($shipmentModel->events->isNotEmpty())
                    <ol class="relative border-l border-zinc-200 dark:border-zinc-700 ml-3 space-y-4">
                        @foreach ($shipmentModel->events as $evt)
                            <li class="ml-4">
                                <div class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border border-white bg-indigo-600 dark:border-zinc-900"></div>
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="text-xs font-bold text-zinc-900 dark:text-white">{{ $evt->provider_status }}</span>
                                    <time class="text-[10px] text-zinc-400">@datetime($evt->occurred_at)</time>
                                </div>
                                @if ($evt->location)
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $evt->location }}</p>
                                @endif
                                @if ($evt->description)
                                    <p class="text-xs text-zinc-600 dark:text-zinc-300 mt-0.5">{{ $evt->description }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="text-xs text-zinc-400">No tracking events recorded yet.</p>
                @endif
            </div>

            <!-- Courier Claims Recorded -->
            @if (! empty($courierClaims))
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                        Courier Claims Filed ({{ count($courierClaims) }})
                    </h3>
                    <div class="space-y-2">
                        @foreach ($courierClaims as $claim)
                            <div class="flex items-center justify-between p-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 text-xs">
                                <div>
                                    <span class="font-bold text-zinc-900 dark:text-white font-mono">{{ $claim->claimNumber }}</span>
                                    <p class="text-zinc-500 text-[11px]">{{ strtoupper($claim->claimType->value) }} &bull; Bearer: {{ strtoupper($claim->bearer) }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-zinc-900 dark:text-white">₹{{ number_format($claim->claimAmountPaise / 100) }}</span>
                                    <p class="text-[10px] text-zinc-400">{{ strtoupper($claim->status->value) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Raw Provider Metadata -->
            @if ($shipmentModel->raw_metadata)
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                        Raw Provider Response
                    </h3>
                    <pre class="bg-zinc-50 dark:bg-zinc-950 p-4 rounded-xl text-[11px] font-mono text-zinc-700 dark:text-zinc-300 overflow-x-auto max-h-60">{{ json_encode($shipmentModel->raw_metadata, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
        </div>

        <!-- Right: Order & Financials Info -->
        <div class="lg:col-span-1 space-y-6">
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                    Order Linkage
                </h3>

                <div class="text-xs space-y-2">
                    <div>
                        <span class="text-zinc-400">Seller Profile ID:</span>
                        <strong class="text-zinc-900 dark:text-white ml-1">#{{ $shipmentModel->seller_profile_id }}</strong>
                    </div>
                    <div>
                        <span class="text-zinc-400">Seller Order ID:</span>
                        <p class="font-mono font-bold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $shipmentModel->seller_order_id }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-400">Parent Order ID:</span>
                        <p class="font-mono text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $shipmentModel->order_id }}</p>
                    </div>
                    <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <span class="text-zinc-400">COD Flag:</span>
                        <strong class="ml-1 {{ $shipmentModel->is_cod ? 'text-amber-600' : 'text-zinc-600' }}">
                            {{ $shipmentModel->is_cod ? 'Yes (₹'.number_format($shipmentModel->cod_amount_paise / 100).')' : 'Prepaid (₹0)' }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
