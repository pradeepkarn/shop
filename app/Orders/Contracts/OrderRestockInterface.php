<?php

namespace App\Orders\Contracts;

interface OrderRestockInterface
{
    /**
     * Restock inventory for items in a seller order and refresh buy box.
     *
     * @param  array<int>|null  $orderItemIds  Specific items to restock, or null for all
     */
    public function restockSellerOrder(string $sellerOrderId, ?array $orderItemIds = null): void;
}
