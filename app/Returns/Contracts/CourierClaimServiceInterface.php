<?php

namespace App\Returns\Contracts;

use App\Returns\DTOs\CourierClaimDto;

interface CourierClaimServiceInterface
{
    public function recordClaim(
        string $shipmentId,
        string $sellerOrderId,
        string $carrierName,
        string $claimType,
        int $claimAmountPaise,
        string $bearer = 'platform',
        ?int $adminId = null,
        ?string $carrierClaimReference = null,
        ?string $notes = null,
    ): CourierClaimDto;

    public function updateClaimStatus(
        string $claimId,
        string $status,
        ?string $carrierClaimReference = null,
        ?string $notes = null,
    ): CourierClaimDto;
}
