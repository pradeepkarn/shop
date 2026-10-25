<?php

use App\Catalog\CatalogServiceProvider;
use App\Fees\FeesServiceProvider;
use App\Orders\OrdersServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Onecentlin\Adminer\ServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    CatalogServiceProvider::class,
    OrdersServiceProvider::class,
    FeesServiceProvider::class,
    ServiceProvider::class,
];
