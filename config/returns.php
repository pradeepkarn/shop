<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Seller RTO Inspection Window
    |--------------------------------------------------------------------------
    | Number of days a seller has to report the received condition of an RTO package.
    */
    'seller_rto_inspection_days' => (int) env('RETURNS_SELLER_RTO_INSPECTION_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Courier Loss Bearer
    |--------------------------------------------------------------------------
    | Who absorbs the item value when a shipment is marked Lost by the courier.
    | Options: 'platform', 'seller', 'courier'
    */
    'courier_loss_bearer' => env('RETURNS_COURIER_LOSS_BEARER', 'platform'),

    /*
    |--------------------------------------------------------------------------
    | RTO Shipping Multiplier
    |--------------------------------------------------------------------------
    | Multiplier applied to forward shipping fee to calculate the RTO shipping debit.
    */
    'rto_shipping_multiplier' => (float) env('RETURNS_RTO_SHIPPING_MULTIPLIER', 1.0),
];
