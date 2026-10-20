<?php

use App\Http\Controllers\SellerDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('seller/apply', 'pages::seller.onboarding')->name('seller.apply');
    Route::get('seller/documents/{document}', [SellerDocumentController::class, 'show'])->name('seller.documents.show');

    Route::middleware(['ensure.seller'])->prefix('seller')->name('seller.')->group(function () {
        Route::livewire('/', 'pages::seller.dashboard')->name('dashboard');
        Route::livewire('profile', 'pages::seller.profile')->name('profile');
        Route::livewire('settings', 'pages::seller.settings')->name('settings');
    });
});
