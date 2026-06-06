<?php

use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Controllers\Stories\LorebookController;
use App\Http\Controllers\Stories\StoryController;
use App\Http\Controllers\Stories\StoryOverviewController;
use App\Http\Controllers\Stories\StoryPlaceholderController;
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

    // Lorebook CRUD (E3.1 / S-3.1.1). Story-scoped world facts injected on
    // keyword match. The child {lorebookEntry} binds via scoped bindings, so an
    // entry from another story resolves to 404; writes are throttled.
    Route::get('stories/{story:slug}/lorebook', [LorebookController::class, 'index'])->name('stories.lorebook.index');
    Route::post('stories/{story:slug}/lorebook', [LorebookController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('stories.lorebook.store');
    Route::put('stories/{story:slug}/lorebook/{lorebookEntry}', [LorebookController::class, 'update'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.lorebook.update');
    Route::delete('stories/{story:slug}/lorebook/{lorebookEntry}', [LorebookController::class, 'destroy'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.lorebook.destroy');

    // Workspace placeholder surfaces (E2.1 / S-2.1.1). Reachable "coming soon"
    // pages so the workspace nav spans every authoring surface without dead
    // links. Repointed at their real controllers when each feature ships (PH-30).
    Route::get('stories/{story:slug}/characters', [StoryPlaceholderController::class, 'characters'])->name('stories.characters.index');
    Route::get('stories/{story:slug}/structure', [StoryPlaceholderController::class, 'structure'])->name('stories.structure.index');
    Route::get('stories/{story:slug}/saves', [StoryPlaceholderController::class, 'saves'])->name('stories.saves.index');

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
