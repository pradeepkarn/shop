<?php

namespace App\Fees;

use App\Fees\Contracts\FeeCalculatorInterface;
use App\Fees\Contracts\FeeScheduleServiceInterface;
use App\Fees\Contracts\OrderItemFeeSnapshotLookupInterface;
use App\Fees\Services\FeeCalculator;
use App\Fees\Services\FeeScheduleService;
use App\Fees\Services\OrderItemFeeSnapshotLookupService;
use Illuminate\Support\ServiceProvider;

class FeesServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        FeeScheduleServiceInterface::class => FeeScheduleService::class,
        FeeCalculatorInterface::class => FeeCalculator::class,
        OrderItemFeeSnapshotLookupInterface::class => OrderItemFeeSnapshotLookupService::class,
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
