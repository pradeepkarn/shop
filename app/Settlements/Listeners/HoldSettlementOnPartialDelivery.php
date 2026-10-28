<?php

namespace App\Settlements\Listeners;

use App\Orders\Events\SellerOrderPartialDelivered;
use App\Settlements\Models\SellerOrderSettlement;

class HoldSettlementOnPartialDelivery
{
    public function handle(SellerOrderPartialDelivered $event): void
    {
        SellerOrderSettlement::where('seller_order_id', $event->sellerOrderId)
            ->update(['is_partial_delivery_hold' => true]);
    }
}
