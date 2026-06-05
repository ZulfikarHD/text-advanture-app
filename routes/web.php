<?php

use App\Http\Controllers\Reviews\ReviewController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Shared review gate (S-6.2). Item routes bind under the owner scope, so a
    // foreign proposal resolves to 404; writes are throttled.
    Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::post('reviews/{reviewItem}/accept', [ReviewController::class, 'accept'])
        ->middleware('throttle:30,1')
        ->name('reviews.accept');
    Route::put('reviews/{reviewItem}', [ReviewController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('reviews.update');
    Route::post('reviews/{reviewItem}/reject', [ReviewController::class, 'reject'])
        ->middleware('throttle:30,1')
        ->name('reviews.reject');
});

require __DIR__.'/settings.php';
