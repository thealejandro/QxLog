<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

Route::middleware(['auth'])->group(function () {
    Volt::route('procedures/create', 'procedures.create')->name('procedures.create');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Volt::route('payouts/create', 'payouts.create')->name('payouts.create');
    Volt::route('payouts/{batch}/voucher', 'payouts.voucher')->name('payouts.voucher');
    Volt::route('payouts', 'payouts.index')->name('payouts.index');

    Volt::route('procedures', 'procedures.index')->name('procedures.index');

    Volt::route('pricing/settings', 'pricing.settings')->name('pricing.settings');
    Volt::route('pricing/instrumentists', 'pricing.instrumentist')->name('pricing.instrumentists');
});

Route::middleware(['auth', 'superadmin'])->group(function () {
    Volt::route('users', 'users.index')->name('users.index');
    Volt::route('users/create', 'users.create')->name('users.create');
    Volt::route('users/{user}/edit', 'users.edit')->name('users.edit');

});
// Volt::route('pricing/create', 'pricing.create')->name('pricing.create');
// Volt::route('pricing/{setting}/edit', 'pricing.edit')->name('pricing.edit');

