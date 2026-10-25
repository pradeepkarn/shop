<?php

use App\Shipping\Http\Controllers\EkartWebhookController;
use App\Shipping\Http\Controllers\ShipmentLabelDownloadController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/shipping/ekart', [EkartWebhookController::class, 'handle'])
    ->name('shipping.webhooks.ekart');

Route::middleware(['auth'])->group(function () {
    Route::get('/shipping/shipments/{shipment}/label', [ShipmentLabelDownloadController::class, 'download'])
        ->name('shipping.shipments.label');
});
