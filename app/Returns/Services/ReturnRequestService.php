<?php

namespace App\Returns\Services;

use App\Models\Admin;
use App\Orders\Contracts\CustomerRefundServiceInterface;
use App\Orders\Contracts\ReturnableOrderLookupInterface;
use App\Orders\Contracts\SellerOrderReturnWindowLookupInterface;
use App\Orders\Enums\SellerOrderStatus;
use App\Returns\Contracts\ReturnRequestServiceInterface;
use App\Returns\Enums\ReturnReason;
use App\Returns\Enums\ReturnRequestStatus;
use App\Returns\Models\ReturnRequest;
use App\Returns\Models\ReturnRequestItem;
use App\Returns\Models\UndeliveredItem;
use App\Services\AdminActivityLogger;
use App\Settlements\Contracts\SettlementServiceInterface;
use App\Shipping\Contracts\ShipmentLookupInterface;
use App\Shipping\Contracts\ShipmentServiceInterface;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ReturnRequestService implements ReturnRequestServiceInterface
{
    public function __construct(
        protected ReturnableOrderLookupInterface $orders,
        protected SellerOrderReturnWindowLookupInterface $currentWindows,
        protected SettlementServiceInterface $settlements,
        protected ShipmentServiceInterface $shipments,
        protected ShipmentLookupInterface $shipmentLookup,
        protected CustomerRefundServiceInterface $refunds,
    ) {}

    public function returnableLines(string $sellerOrderId): array
    {
        $order = $this->orders->forSellerOrder($sellerOrderId);
        if (! $order) {
            return [];
        }

        $delivered = in_array($order->status, [SellerOrderStatus::Delivered, SellerOrderStatus::PartiallyDelivered], true)
            && $order->deliveredAt !== null;

        // The window saved when the package was delivered; later vertical edits never change it.
        // Only if there is no settlement (fees never calculated) do the verticals' current windows apply.
        $windowDays = $this->settlements->savedReturnWindowDays($sellerOrderId) ?? $this->currentWindows->returnWindowDays($sellerOrderId);

        $undelivered = UndeliveredItem::where('seller_order_id', $sellerOrderId)->get();
        $inReturns = ReturnRequestItem::query()
            ->join('return_requests', 'return_requests.id', '=', 'return_request_items.return_request_id')
            ->where('return_requests.seller_order_id', $sellerOrderId)
            ->whereNotIn('return_requests.status', [ReturnRequestStatus::Rejected->value, ReturnRequestStatus::Closed->value])
            ->selectRaw('return_request_items.order_item_id, sum(return_request_items.units) as units')
            ->groupBy('return_request_items.order_item_id')
            ->pluck('units', 'order_item_id');

        $lines = [];
        foreach ($order->items as $orderItemId => $item) {
            $lineUndelivered = $undelivered->where('order_item_id', $orderItemId);
            $notDelivered = (int) $lineUndelivered->where('status', '!=', UndeliveredItem::STATUS_RESHIPPED)->sum('units');

            // Reshipped units were delivered later: their window runs from the reship delivery
            $reshipped = $lineUndelivered->firstWhere('status', UndeliveredItem::STATUS_RESHIPPED);
            $reshipDeliveredAt = $reshipped?->reship_shipment_id
                ? $this->shipmentLookup->getShipmentById($reshipped->reship_shipment_id)?->deliveredAt
                : null;
            $deliveredAt = $reshipDeliveredAt ?? $order->deliveredAt;

            $lines[$orderItemId] = [
                'title' => $item->title,
                'available' => $delivered ? max(0, $item->qty - $notDelivered - (int) ($inReturns[$orderItemId] ?? 0)) : 0,
                'deadline' => $delivered && $deliveredAt ? CarbonImmutable::instance($deliveredAt)->addDays($windowDays) : null,
                'returnable' => $item->isReturnable,
            ];
        }

        return $lines;
    }

    public function create(
        int $userId,
        string $sellerOrderId,
        ReturnReason $reason,
        string $preference,
        array $units,
        array $photos = [],
        ?string $notes = null,
        ?int $overrideByAdminId = null,
    ): ReturnRequest {
        $order = $this->orders->forSellerOrder($sellerOrderId);
        if (! $order || $order->userId !== $userId) {
            throw new DomainException('This order does not belong to you.');
        }

        if (! in_array($preference, [ReturnRequest::PREFERENCE_REFUND, ReturnRequest::PREFERENCE_REPLACEMENT], true)) {
            throw new DomainException('Choose a refund or a replacement.');
        }

        $units = array_filter(array_map('intval', $units), fn (int $n) => $n > 0);
        if ($units === []) {
            throw new DomainException('Choose at least one item to return.');
        }

        if ($reason->requiresPhotos()) {
            Validator::make(['photos' => $photos], [
                'photos' => ['required', 'array', 'min:1', 'max:5'],
                'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ], [
                'photos.required' => 'Add at least one photo showing the problem.',
                'photos.min' => 'Add at least one photo showing the problem.',
            ])->validate();
        } elseif (count($photos) > 0) {
            Validator::make(['photos' => $photos], [
                'photos' => ['array', 'max:5'],
                'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ])->validate();
        }

        // Photos go to the private disk first; removed again if the request is refused
        $requestId = (string) Str::ulid();
        $storedPhotos = array_map(fn ($photo) => $photo->store("return_photos/{$requestId}", 'local'), $photos);

        try {
            $request = DB::transaction(function () use ($requestId, $userId, $order, $reason, $preference, $units, $storedPhotos, $notes, $overrideByAdminId) {
                $hasActive = ReturnRequest::where('seller_order_id', $order->sellerOrderId)
                    ->whereIn('status', ReturnRequestStatus::activeValues())
                    ->lockForUpdate()
                    ->exists();
                if ($hasActive) {
                    throw new DomainException('This package already has an open return.');
                }

                $lines = $this->returnableLines($order->sellerOrderId);
                $deadlines = [];

                foreach ($units as $orderItemId => $count) {
                    $line = $lines[$orderItemId] ?? null;
                    if (! $line) {
                        throw new DomainException('That item is not part of this package.');
                    }

                    if ($count > $line['available']) {
                        throw new DomainException("Only {$line['available']} unit(s) of {$line['title']} can be returned.");
                    }

                    if (! $line['returnable'] && ! $reason->isClaim()) {
                        throw new DomainException("{$line['title']} can only be returned if it arrived damaged, defective, wrong or incomplete.");
                    }

                    if ($line['deadline'] && now()->greaterThan($line['deadline']) && ! $overrideByAdminId) {
                        throw new DomainException("The return window for {$line['title']} closed on ".format_datetime($line['deadline']).'.');
                    }

                    $deadlines[] = $line['deadline'];
                }

                /** @var ReturnRequest $request */
                $request = ReturnRequest::forceCreate([
                    'id' => $requestId,
                    'order_id' => $order->orderId,
                    'seller_order_id' => $order->sellerOrderId,
                    'user_id' => $userId,
                    'reason' => $reason,
                    'preference' => $preference,
                    'status' => ReturnRequestStatus::Requested,
                    'customer_notes' => $notes,
                    'photos' => $storedPhotos,
                    'return_deadline_at' => collect($deadlines)->filter()->min(),
                    'window_override_by_admin_id' => $overrideByAdminId,
                ]);

                foreach ($units as $orderItemId => $count) {
                    ReturnRequestItem::create([
                        'return_request_id' => $request->id,
                        'order_item_id' => $orderItemId,
                        'units' => $count,
                        'line_qty' => $order->items[$orderItemId]->qty,
                    ]);
                }

                $this->settlements->placeReturnHold($order->sellerOrderId);

                return $request;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($storedPhotos);

            throw $e;
        }

        if ($overrideByAdminId) {
            AdminActivityLogger::log('returns.window_override', $request, [
                'seller_order_id' => $sellerOrderId,
                'units' => $units,
                'deadline' => $request->return_deadline_at?->toIso8601String(),
            ], Admin::find($overrideByAdminId));
        }

        return $request->load('items');
    }

    public function requestInfo(string $returnRequestId, string $message, int $adminId): ReturnRequest
    {
        $message = trim($message);
        if ($message === '') {
            throw new DomainException('Tell the customer what information is needed.');
        }

        return DB::transaction(function () use ($returnRequestId, $message, $adminId) {
            $request = $this->lockInStatus($returnRequestId, [ReturnRequestStatus::Requested]);
            $request->update([
                'status' => ReturnRequestStatus::InfoRequested,
                'info_request_message' => $message,
                'decided_by_admin_id' => $adminId,
            ]);

            return $request->fresh();
        });
    }

    public function respond(string $returnRequestId, int $userId, string $response): ReturnRequest
    {
        $response = trim($response);
        if ($response === '') {
            throw new DomainException('Add your answer before sending.');
        }

        return DB::transaction(function () use ($returnRequestId, $userId, $response) {
            $request = $this->lockInStatus($returnRequestId, [ReturnRequestStatus::InfoRequested]);
            if ($request->user_id !== $userId) {
                throw new DomainException('This return does not belong to you.');
            }

            $request->update(['status' => ReturnRequestStatus::Requested, 'customer_response' => $response]);

            return $request->fresh();
        });
    }

    public function approve(string $returnRequestId, int $adminId): ReturnRequest
    {
        $request = DB::transaction(function () use ($returnRequestId, $adminId) {
            $request = $this->lockInStatus($returnRequestId, [ReturnRequestStatus::Requested, ReturnRequestStatus::InfoRequested]);
            $request->load('items');
            $units = $request->items->pluck('units', 'order_item_id')->all();
            $decision = ['decided_by_admin_id' => $adminId, 'decided_at' => now()];

            if (! $request->reason->needsPickup()) {
                // Missing item: the seller did not ship it. Reverse sale and fees for those units, then hand them to the
                // refund/reship queue (the customer paid for them).
                $this->settlements->reverseUnitsForReturnClaim($request->seller_order_id, $request->id, $request->lines());
                $originalShipmentId = $this->shipmentLookup->getShipmentForSellerOrder($request->seller_order_id)?->id;

                foreach ($request->items as $item) {
                    UndeliveredItem::create([
                        'seller_order_id' => $request->seller_order_id,
                        'shipment_id' => (string) $originalShipmentId,
                        'order_item_id' => $item->order_item_id,
                        'units' => $item->units,
                        'line_qty' => $item->line_qty,
                        'status' => UndeliveredItem::STATUS_PENDING,
                        'origin' => UndeliveredItem::ORIGIN_MISSING_ITEM_CLAIM,
                        'customer_paid' => true,
                        'return_request_id' => $request->id,
                        'recorded_by_admin_id' => $adminId,
                    ]);
                }

                $request->update(['status' => ReturnRequestStatus::Resolved] + $decision);
                $this->settlements->releaseReturnHold($request->seller_order_id);

                return $request;
            }

            $pickup = $this->shipments->createReturnPickupBooking(
                sellerOrderId: $request->seller_order_id,
                units: $units,
                declaredValuePaise: $this->refunds->unitsValue($request->seller_order_id, $units),
                returnReason: $request->reason->label(),
            );

            $request->update(['status' => ReturnRequestStatus::Approved, 'reverse_shipment_id' => $pickup->id] + $decision);

            return $request;
        });

        // Courier booking after commit, no locks held; a failure leaves a retryable booking on the shipment page
        if ($request->reverse_shipment_id && $request->status === ReturnRequestStatus::Approved) {
            $this->shipments->bookShipment($request->reverse_shipment_id);
        }

        return $request->fresh();
    }

    public function reject(string $returnRequestId, string $reason, int $adminId): ReturnRequest
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Give the customer a reason for the rejection.');
        }

        return DB::transaction(function () use ($returnRequestId, $reason, $adminId) {
            $request = $this->lockInStatus($returnRequestId, [ReturnRequestStatus::Requested, ReturnRequestStatus::InfoRequested]);
            $request->update([
                'status' => ReturnRequestStatus::Rejected,
                'rejection_reason' => $reason,
                'decided_by_admin_id' => $adminId,
                'decided_at' => now(),
            ]);
            $this->settlements->releaseReturnHold($request->seller_order_id);

            return $request->fresh();
        });
    }

    public function pickupCancelled(string $reverseShipmentId, ?string $reason = null): void
    {
        DB::transaction(function () use ($reverseShipmentId, $reason) {
            /** @var ReturnRequest|null $request */
            $request = ReturnRequest::where('reverse_shipment_id', $reverseShipmentId)
                ->where('status', ReturnRequestStatus::Approved->value)
                ->lockForUpdate()
                ->first();
            if (! $request) {
                return;
            }

            $request->update([
                'status' => ReturnRequestStatus::Closed,
                'closed_reason' => $reason ?: 'Return pickup could not be completed',
            ]);
            $this->settlements->releaseReturnHold($request->seller_order_id);
        });
    }

    public function pickupReceived(string $reverseShipmentId): void
    {
        ReturnRequest::where('reverse_shipment_id', $reverseShipmentId)
            ->where('status', ReturnRequestStatus::Approved->value)
            ->update(['status' => ReturnRequestStatus::Received->value]);
    }

    public function hasOpenReturns(int $userId): bool
    {
        return ReturnRequest::where('user_id', $userId)->whereIn('status', ReturnRequestStatus::activeValues())->exists();
    }

    /**
     * @param  list<ReturnRequestStatus>  $allowed
     */
    protected function lockInStatus(string $returnRequestId, array $allowed): ReturnRequest
    {
        /** @var ReturnRequest $request */
        $request = ReturnRequest::lockForUpdate()->findOrFail($returnRequestId);

        if (! in_array($request->status, $allowed, true)) {
            throw new DomainException("This return is already {$request->status->label()}.");
        }

        return $request;
    }
}
