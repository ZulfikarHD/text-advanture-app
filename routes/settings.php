<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ProviderController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/provider', [ProviderController::class, 'edit'])->name('provider.edit');
    Route::put('settings/provider', [ProviderController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('provider.update');
    Route::delete('settings/provider', [ProviderController::class, 'destroy'])->name('provider.destroy');
});
