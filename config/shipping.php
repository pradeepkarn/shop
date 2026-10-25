<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Shipping Carrier Driver
    |--------------------------------------------------------------------------
    |
    | Supported: 'ekart', 'manual', 'fake'
    |
    */

    'default' => env('SHIPPING_CARRIER_DEFAULT', 'ekart'),

    /*
    |--------------------------------------------------------------------------
    | Safe Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, real API calls that create packages/shipments on Ekart's
    | production endpoint are blocked to prevent accidental real orders.
    | Must be explicitly set to false to allow live API shipment creation.
    |
    */

    'safe_mode' => (bool) env('SHIPPING_SAFE_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Serviceability Cache TTL
    |--------------------------------------------------------------------------
    |
    | Duration (in hours) to cache pincode serviceability results.
    |
    */

    'serviceability_cache_ttl_hours' => 4,

    /*
    |--------------------------------------------------------------------------
    | Hard Limits
    |--------------------------------------------------------------------------
    |
    | Maximum allowed Cash on Delivery (COD) amount in INR per package.
    | Ekart hard limit: ₹49,999.
    |
    */

    'cod_max_limit_inr' => 49999,

    /*
    |--------------------------------------------------------------------------
    | Volumetric Divisor
    |--------------------------------------------------------------------------
    |
    | Standard divisor for volumetric weight: (L * W * H in cm) / 5000 = kg.
    |
    */

    'volumetric_divisor' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Ekart Carrier Settings
    |--------------------------------------------------------------------------
    |
    | Credentials and configuration for Ekart Logistics API.
    |
    */

    'ekart' => [
        'base_url' => env('EKART_BASE_URL', 'https://app.elite.ekartlogistics.in'),
        'client_id' => env('EKART_CLIENT_ID', ''),
        'username' => env('EKART_USERNAME', ''),
        'password' => env('EKART_PASSWORD', ''),
        'service' => env('EKART_SERVICE', 'SURFACE'), // SURFACE or EXPRESS
        'webhook_secret' => env('EKART_WEBHOOK_SECRET', ''),
        'webhook_header' => env('EKART_WEBHOOK_HEADER', null),
        'webhook_algorithm' => env('EKART_WEBHOOK_ALGO', null),
        'timeout_seconds' => (int) env('EKART_TIMEOUT_SECONDS', 15),
        'retry_attempts' => (int) env('EKART_RETRY_ATTEMPTS', 3),
    ],
];
