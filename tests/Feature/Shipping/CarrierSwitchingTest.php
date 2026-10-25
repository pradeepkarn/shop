<?php

use App\Shipping\Contracts\CarrierManagerInterface;
use App\Shipping\Drivers\EkartCarrierDriver;
use App\Shipping\Drivers\FakeCarrierDriver;
use App\Shipping\Drivers\ManualCarrierDriver;

test('carrier manager resolves drivers correctly and supports runtime switching', function () {
    $manager = app(CarrierManagerInterface::class);

    $ekart = $manager->driver('ekart');
    expect($ekart)->toBeInstanceOf(EkartCarrierDriver::class)
        ->and($ekart->name())->toBe('ekart');

    $manual = $manager->driver('manual');
    expect($manual)->toBeInstanceOf(ManualCarrierDriver::class)
        ->and($manual->name())->toBe('manual');

    $fake = $manager->driver('fake');
    expect($fake)->toBeInstanceOf(FakeCarrierDriver::class)
        ->and($fake->name())->toBe('fake');
});
