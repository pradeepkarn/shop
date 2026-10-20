<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:admin')->group(function () {
    Route::livewire('login', 'pages::admin.login')->name('login');
});

Route::middleware('auth:admin')->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('customers', 'pages::admin.customers')->name('customers');
    Route::livewire('customers/{user}', 'pages::admin.customer-details')->name('customers.show');
    Route::livewire('profile', 'pages::admin.profile')->name('profile');

    Route::post('logout', function (Request $request) {
        Auth::guard('admin')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    })->name('logout');
});

