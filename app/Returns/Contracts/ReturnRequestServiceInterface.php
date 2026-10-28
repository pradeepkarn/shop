<?php

namespace App\Returns\Contracts;

use App\Returns\Enums\ReturnReason;
use App\Returns\Models\ReturnRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

interface ReturnRequestServiceInterface
{
    /**
     * What can still be returned from a package, per line: units available (delivered − in returns − not delivered),
     * the return deadline (delivery date + the window saved at delivery) and whether the vertical is returnable.
     *
     * @return array<int, array{title: string, available: int, deadline: ?CarbonImmutable, returnable: bool}>
     */
    public function returnableLines(string $sellerOrderId): array;

    /**
     * Customer requests a return of units of one package. Places the return hold on the package settlement.
     * $overrideByAdminId lets an admin create it after the window closed (logged).
     *
     * @param  array<int, int>  $units  order item id => units
     * @param  array<int, UploadedFile>  $photos
     */
    public function create(
        int $userId,
        string $sellerOrderId,
        ReturnReason $reason,
        string $preference,
        array $units,
        array $photos = [],
        ?string $notes = null,
        ?int $overrideByAdminId = null,
    ): ReturnRequest;

    public function requestInfo(string $returnRequestId, string $message, int $adminId): ReturnRequest;

    /**
     * Customer answers an information request; the request goes back to review.
     */
    public function respond(string $returnRequestId, int $userId, string $response): ReturnRequest;

    /**
     * Approve: book the return pickup (outside any transaction), or for a missing item reverse the seller's sale and
     * fees for those units and hand them to the refund/reship queue.
     */
    public function approve(string $returnRequestId, int $adminId): ReturnRequest;

    public function reject(string $returnRequestId, string $reason, int $adminId): ReturnRequest;

    /**
     * The return pickup failed for good (or was cancelled): the request closes and the hold is released.
     */
    public function pickupCancelled(string $reverseShipmentId, ?string $reason = null): void;

    /**
     * The return pickup reached the seller.
     */
    public function pickupReceived(string $reverseShipmentId): void;

    /**
     * Any open return for this customer (blocks account deletion).
     */
    public function hasOpenReturns(int $userId): bool;
}
