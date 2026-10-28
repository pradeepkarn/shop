<?php

namespace App\Orders\Contracts;

use App\Orders\DTOs\CustomerRefundDto;

interface CustomerRefundServiceInterface
{
    /**
     * Refund units of a seller order's items for one source (e.g. an undelivered item or a return item).
     * Amounts are coupon-aware per unit; previously refunded units are read from stored refunds;
     * coupon usage is released with the Coupons partial-return rule. One refund per source.
     *
     * @param  array<int, int>  $units  order item id => units to refund now
     * @param  bool  $customerPaid  false when the customer was never charged for these units (status not_collected)
     */
    public function createRefund(string $sourceType, string $sourceId, string $sellerOrderId, array $units, bool $customerPaid): CustomerRefundDto;

    /**
     * Units of an order item already covered by refunds.
     */
    public function refundedUnits(int $orderItemId): int;

    /**
     * Customer sets where the refund goes (UPI or bank). Validated; stored encrypted. A failed refund goes
     * back to pending for retry.
     *
     * @param  array<string, mixed>  $details  payout_method (upi|bank), upi_id | bank_account_holder, bank_account_number, bank_ifsc
     */
    public function updatePayoutDetails(string $refundId, int $userId, array $details): CustomerRefundDto;

    /**
     * Admin records a manual payout with its UTR. Customer and refund managers are notified.
     */
    public function markPaid(string $refundId, string $utr, int $adminId): CustomerRefundDto;

    /**
     * Admin records a failed payout. Customer and refund managers are notified; the customer can update details to retry.
     */
    public function markFailed(string $refundId, string $reason, int $adminId): CustomerRefundDto;
}
