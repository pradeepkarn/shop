<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('products', 'pages::storefront.product-index')->name('products.index');
Route::livewire('{slug}/p/{upid}', 'pages::storefront.product-show')->name('product.show');

Route::livewire('welcome/{token}', 'pages::auth.welcome')->name('welcome');

Route::livewire('force-change-password', 'pages::auth.force-change-password')
    ->name('password.force-change')
    ->middleware('auth');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});
require __DIR__.'/settings.php';
require __DIR__.'/seller.php';
