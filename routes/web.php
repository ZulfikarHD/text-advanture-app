<?php

use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Controllers\Stories\CharacterController;
use App\Http\Controllers\Stories\LorebookController;
use App\Http\Controllers\Stories\RevealLedgerController;
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

    // Character CRUD (E1.1 / S-1.1.1 / S-1.1.2). Minimal manual cast — name,
    // appearance, folded identity, mandatory knowledge_boundary, exactly one
    // is_player; no LLM call. The child {character} binds via scoped bindings, so
    // a character from another story resolves to 404; writes are throttled.
    Route::get('stories/{story:slug}/characters', [CharacterController::class, 'index'])->name('stories.characters.index');
    Route::post('stories/{story:slug}/characters', [CharacterController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('stories.characters.store');
    Route::put('stories/{story:slug}/characters/{character}', [CharacterController::class, 'update'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.characters.update');
    Route::delete('stories/{story:slug}/characters/{character}', [CharacterController::class, 'destroy'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.characters.destroy');

    // Lorebook CRUD (E3.1 / S-3.1.1). Story-scoped world facts injected on
    // keyword match. The child {lorebookEntry} binds via scoped bindings, so an
    // entry from another story resolves to 404; writes are throttled.
    Route::get('stories/{story:slug}/lorebook', [LorebookController::class, 'index'])->name('stories.lorebook.index');
    Route::post('stories/{story:slug}/lorebook', [LorebookController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('stories.lorebook.store');
    // Keyword match preview (S-3.2.1): a read-only standalone request (useHttp)
    // that mirrors runtime injection so the author can tune keywords before play.
    Route::post('stories/{story:slug}/lorebook/preview', [LorebookController::class, 'preview'])
        ->middleware('throttle:30,1')
        ->name('stories.lorebook.preview');
    Route::put('stories/{story:slug}/lorebook/{lorebookEntry}', [LorebookController::class, 'update'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.lorebook.update');
    Route::delete('stories/{story:slug}/lorebook/{lorebookEntry}', [LorebookController::class, 'destroy'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.lorebook.destroy');

    // Reveal-ledger CRUD (E4.1 / S-4.1.1). Story-scoped load-bearing secrets
    // that make spoiler-safety explicit. The child {revealLedgerEntry} binds via
    // scoped bindings (through Story::revealLedgerEntries()), so an entry from
    // another story resolves to 404; writes are throttled.
    Route::get('stories/{story:slug}/reveal-ledger', [RevealLedgerController::class, 'index'])->name('stories.reveal-ledger.index');
    Route::post('stories/{story:slug}/reveal-ledger', [RevealLedgerController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('stories.reveal-ledger.store');
    Route::put('stories/{story:slug}/reveal-ledger/{revealLedgerEntry}', [RevealLedgerController::class, 'update'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.reveal-ledger.update');
    Route::delete('stories/{story:slug}/reveal-ledger/{revealLedgerEntry}', [RevealLedgerController::class, 'destroy'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.reveal-ledger.destroy');

    // Workspace placeholder surfaces (E2.1 / S-2.1.1). Reachable "coming soon"
    // pages so the workspace nav spans every authoring surface without dead
    // links. Repointed at their real controllers when each feature ships (PH-30).
    // Characters shipped in E1.1 (see the Character CRUD block above).
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
