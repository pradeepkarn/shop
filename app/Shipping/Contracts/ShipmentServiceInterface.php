<?php

namespace App\Shipping\DTOs;

namespace App\Shipping\Contracts;

use App\Shipping\DTOs\ShipmentSummaryDto;
use App\Shipping\DTOs\TrackingStatusDto;
use App\Shipping\Enums\ShipmentStatus;
use App\Shipping\Models\Shipment;
use App\Shipping\Models\ShipmentEvent;

interface ShipmentServiceInterface
{
    /**
     * Create and manifest a shipment for a seller order.
     * Generates invoice number, calculates volumetric & chargeable weights,
     * calls carrier driver, stores shipment, downloads label, and marks seller order as shipped.
     */
    public function createShipmentForSellerOrder(
        string $sellerOrderId,
        int $actualWeightGrams,
        int $lengthCm,
        int $widthCm,
        int $heightCm,
        ?string $pickupDate = null,
    ): Shipment;

    /**
     * Reship undelivered units of a partially delivered order: a forward shipment (purpose reship, parent = the original
     * shipment) carrying only those units, collecting $codAmountPaise. The seller order status is not changed.
     *
     * @param  array<int, int>  $units  order item id => units
     */
    public function createReshipShipment(string $sellerOrderId, array $units, int $codAmountPaise, int $declaredValuePaise): ShipmentSummaryDto;

    /**
     * Cancel an active shipment. Reverts seller order to Packed (a cancelled reship only reports the cancellation).
     */
    public function cancelShipment(
        string $shipmentId,
        string $actorType,
        int $actorId,
        ?string $reason = null,
    ): void;

    /**
     * Poll carrier API for latest tracking status and record events.
     */
    public function syncTracking(Shipment $shipment): void;

    /**
     * Record a shipment tracking event idempotently and update shipment/order status.
     */
    public function recordShipmentEvent(Shipment $shipment, TrackingStatusDto $trackingDto): ?ShipmentEvent;

    /**
     * Fetch label PDF binary data or download path for a shipment.
     */
    public function getLabelPdf(Shipment $shipment): string;

    /**
     * Action an NDR delivery failure (reattempt or rto).
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string}
     */
    public function actionNdr(string $shipmentId, string $action, array $payload = []): array;

    /**
     * Advance a fake carrier shipment through simulated statuses.
     *
     * @param  array<string, mixed>  $extra
     */
    public function simulateStatusTransition(
        string $shipmentId,
        ShipmentStatus $status,
        array $extra = []
    ): ShipmentEvent;

    /**
     * Admin confirms which items were delivered on a shipment the courier marked Partially Delivered.
     * Recorded as its own partial_delivery_resolved event; it is not a courier event.
     *
     * @param  array<int>  $deliveredItemIds
     * @param  array<int>  $undeliveredItemIds
     */
    public function resolvePartialDelivery(
        string $shipmentId,
        array $deliveredItemIds,
        array $undeliveredItemIds,
        int $adminId,
        ?string $notes = null
    ): ShipmentEvent;
}
