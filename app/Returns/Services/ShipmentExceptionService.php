<?php

namespace App\Returns\Services;

use App\Orders\Contracts\SellerOrderFulfillmentInterface;
use App\Returns\Contracts\CourierClaimServiceInterface;
use App\Returns\Contracts\ShipmentExceptionServiceInterface;
use App\Returns\Models\PlatformLoss;
use App\Returns\Models\UndeliveredItem;
use App\Settlements\Contracts\SettlementServiceInterface;
use App\Shipping\Contracts\ShipmentServiceInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class ShipmentExceptionService implements ShipmentExceptionServiceInterface
{
    public function __construct(
        protected SettlementServiceInterface $settlementService,
        protected SellerOrderFulfillmentInterface $orderFulfillment,
        protected CourierClaimServiceInterface $claimService,
        protected ShipmentServiceInterface $shipmentService,
    ) {}

    public function confirmLostShipment(
        string $shipmentId,
        string $sellerOrderId,
        string $carrierName,
        int $declaredValuePaise,
        int $adminId,
        ?string $notes = null,
    ): void {
        if (config('returns.courier_loss_bearer', 'platform') !== 'platform') {
            throw new DomainException('Only platform-borne courier losses are supported.');
        }

        DB::transaction(function () use ($shipmentId, $sellerOrderId, $carrierName, $declaredValuePaise, $adminId, $notes) {
            $alreadyConfirmed = PlatformLoss::where('shipment_id', $shipmentId)
                ->where('loss_type', PlatformLoss::TYPE_LOST_SHIPMENT)
                ->lockForUpdate()
                ->exists();
            if ($alreadyConfirmed) {
                throw new DomainException('This lost shipment has already been confirmed.');
            }

            $settlement = $this->settlementService->confirmLostShipmentSettlement($sellerOrderId, $adminId, $notes);

            $this->orderFulfillment->markLostInTransit($sellerOrderId, 'Shipment lost in transit by courier; customer not charged');

            $claim = $this->claimService->recordClaim(
                shipmentId: $shipmentId,
                sellerOrderId: $sellerOrderId,
                carrierName: $carrierName,
                claimType: 'lost_shipment',
                claimAmountPaise: $declaredValuePaise,
                bearer: 'platform',
                adminId: $adminId,
                notes: $notes ?? 'Shipment confirmed lost by administration (Platform bears loss)',
            );

            PlatformLoss::create([
                'loss_type' => PlatformLoss::TYPE_LOST_SHIPMENT,
                'seller_order_id' => $sellerOrderId,
                'shipment_id' => $shipmentId,
                'courier_claim_id' => $claim->id,
                // The platform pays the seller this payout but collects nothing from the customer
                'loss_amount_paise' => max(0, (int) ($settlement->net_payout ?? 0)),
                'declared_value_paise' => $declaredValuePaise,
                'admin_id' => $adminId,
                'notes' => $notes,
            ]);
        });
    }

    public function resolvePartialDelivery(
        string $shipmentId,
        string $sellerOrderId,
        array $deliveredItemIds,
        array $undeliveredItemIds,
        int $adminId,
        ?string $notes = null,
    ): void {
        DB::transaction(function () use ($shipmentId, $sellerOrderId, $deliveredItemIds, $undeliveredItemIds, $adminId, $notes) {
            $this->shipmentService->resolvePartialDelivery($shipmentId, $deliveredItemIds, $undeliveredItemIds, $adminId, $notes);

            $this->orderFulfillment->confirmPartialDelivery($sellerOrderId, $deliveredItemIds, $undeliveredItemIds);

            $this->settlementService->releasePartialDeliveryHold($sellerOrderId, $undeliveredItemIds);

            foreach ($undeliveredItemIds as $orderItemId) {
                UndeliveredItem::create([
                    'seller_order_id' => $sellerOrderId,
                    'shipment_id' => $shipmentId,
                    'order_item_id' => (int) $orderItemId,
                    'status' => UndeliveredItem::STATUS_PENDING,
                    'recorded_by_admin_id' => $adminId,
                ]);
            }
        });
    }
}
