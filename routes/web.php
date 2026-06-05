<?php

use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Controllers\Stories\StoryController;
use App\Http\Controllers\Stories\StoryOverviewController;
use App\Http\Controllers\Stories\StorySettingsController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    // Workspace dashboard — story list (S-1.1.2).
    Route::get('dashboard', [StoryController::class, 'index'])->name('dashboard');

    // Story CRUD (S-1.1.1 / S-1.1.2). Bind by slug under the owner scope so a
    // foreign story resolves to 404 without leaking existence.
    Route::post('stories', [StoryController::class, 'store'])->name('stories.store');
    Route::get('stories/{story:slug}/edit', [StoryController::class, 'edit'])->name('stories.edit');
    Route::put('stories/{story:slug}', [StoryController::class, 'update'])->name('stories.update');
    Route::delete('stories/{story:slug}', [StoryController::class, 'destroy'])->name('stories.destroy');

    // Per-story workspace surfaces (E1.2). Overview is the workspace entry; the
    // settings screen carries default POV + model-role overrides.
    Route::get('stories/{story:slug}', [StoryOverviewController::class, 'show'])->name('stories.show');
    Route::get('stories/{story:slug}/settings', [StorySettingsController::class, 'edit'])->name('stories.settings.edit');
    Route::put('stories/{story:slug}/settings', [StorySettingsController::class, 'update'])
        ->middleware('throttle:12,1')
        ->name('stories.settings.update');

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
