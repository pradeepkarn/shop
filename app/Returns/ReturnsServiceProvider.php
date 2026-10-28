<?php

namespace App\Returns;

use App\Returns\Contracts\CourierClaimServiceInterface;
use App\Returns\Contracts\ReturnsLookupInterface;
use App\Returns\Contracts\RtoIntakeServiceInterface;
use App\Returns\Contracts\ShipmentExceptionServiceInterface;
use App\Returns\Contracts\UndeliveredItemServiceInterface;
use App\Returns\Listeners\HandleReshipOutcome;
use App\Returns\Services\CourierClaimService;
use App\Returns\Services\ReturnsLookupService;
use App\Returns\Services\RtoIntakeService;
use App\Returns\Services\ShipmentExceptionService;
use App\Returns\Services\UndeliveredItemService;
use App\Shipping\Events\ShipmentReshipCancelled;
use App\Shipping\Events\ShipmentReshipDelivered;
use App\Shipping\Events\ShipmentReshipReturned;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ReturnsServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ReturnsLookupInterface::class => ReturnsLookupService::class,
        RtoIntakeServiceInterface::class => RtoIntakeService::class,
        CourierClaimServiceInterface::class => CourierClaimService::class,
        ShipmentExceptionServiceInterface::class => ShipmentExceptionService::class,
        UndeliveredItemServiceInterface::class => UndeliveredItemService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/returns.php', 'returns');
    }

    public function boot(): void
    {
        Event::listen(ShipmentReshipDelivered::class, [HandleReshipOutcome::class, 'delivered']);
        Event::listen(ShipmentReshipReturned::class, [HandleReshipOutcome::class, 'returned']);
        Event::listen(ShipmentReshipCancelled::class, [HandleReshipOutcome::class, 'cancelled']);
    }
}
