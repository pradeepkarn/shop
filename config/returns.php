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

    /*
    |--------------------------------------------------------------------------
    | Reship Shipping Multiplier
    |--------------------------------------------------------------------------
    | Reship of undelivered items: the seller is charged the forward shipping originally allocated to
    | those units times this multiplier, when the reship is created.
    */
    'reship_shipping_multiplier' => (float) env('RETURNS_RESHIP_SHIPPING_MULTIPLIER', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Return Windows
    |--------------------------------------------------------------------------
    | Days after delivery a customer may request a return. Each vertical sets its own window
    | (verticals.return_window_days); verticals without one use the default. Non-returnable
    | verticals still accept damaged/defective/wrong/missing claims for claim_window_days.
    | A package's settlement hold is the longest window among its items.
    */
    'default_return_window_days' => (int) env('RETURNS_DEFAULT_WINDOW_DAYS', 7),
    'claim_window_days' => (int) env('RETURNS_CLAIM_WINDOW_DAYS', 7),
];
