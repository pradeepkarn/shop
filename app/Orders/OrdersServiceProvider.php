<?php

namespace App\Orders;

use App\Orders\Contracts\OrderItemsFeeLookupInterface;
use App\Orders\Services\OrderItemsFeeLookupService;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        OrderItemsFeeLookupInterface::class => OrderItemsFeeLookupService::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
