<?php

use App\Http\Controllers\Settings\ModelRoleController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ProviderController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\UsageController;
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

    // Provider, Model Roles, and Usage are top-level sidebar items, not settings
    // sub-pages. They stay in the Settings controllers but route outside /settings/*.
    Route::get('provider', [ProviderController::class, 'edit'])->name('provider.edit');
    Route::put('provider', [ProviderController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('provider.update');
    Route::delete('provider', [ProviderController::class, 'destroy'])->name('provider.destroy');
    Route::post('provider/test', [ProviderController::class, 'test'])
        ->middleware('throttle:6,1')
        ->name('provider.test');
    Route::get('provider/models', [ProviderController::class, 'models'])
        ->middleware('throttle:30,1')
        ->name('provider.models');

    Route::get('model-roles', [ModelRoleController::class, 'edit'])->name('model-roles.edit');
    Route::put('model-roles', [ModelRoleController::class, 'update'])
        ->middleware('throttle:12,1')
        ->name('model-roles.update');

    Route::get('usage', [UsageController::class, 'index'])->name('usage.index');
});
