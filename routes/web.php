<?php

use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Controllers\Stories\CharacterController;
use App\Http\Controllers\Stories\LorebookController;
use App\Http\Controllers\Stories\RevealLedgerController;
use App\Http\Controllers\Stories\SessionController;
use App\Http\Controllers\Stories\StoryController;
use App\Http\Controllers\Stories\StoryOverviewController;
use App\Http\Controllers\Stories\StorySettingsController;
use App\Http\Controllers\Stories\StructureController;
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

    // Structure CRUD (E1.2 / S-1.2.1). Minimal manual chapter → scene → beat —
    // scene POV contract (pov_mode/pov_anchor/tone) + present cast, beat goal;
    // no LLM call. Nested children bind via scoped bindings down the
    // {story}→{chapter}→{scene}→{beat} chain, so a row from another story (or a
    // mismatched parent) resolves to 404; writes are throttled.
    Route::get('stories/{story:slug}/structure', [StructureController::class, 'index'])->name('stories.structure.index');
    Route::post('stories/{story:slug}/structure/chapters', [StructureController::class, 'storeChapter'])
        ->middleware('throttle:30,1')
        ->name('stories.structure.chapters.store');
    Route::put('stories/{story:slug}/structure/chapters/{chapter}', [StructureController::class, 'updateChapter'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.structure.chapters.update');
    Route::delete('stories/{story:slug}/structure/chapters/{chapter}', [StructureController::class, 'destroyChapter'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.structure.chapters.destroy');
    Route::post('stories/{story:slug}/structure/chapters/{chapter}/scenes', [StructureController::class, 'storeScene'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.structure.scenes.store');
    Route::put('stories/{story:slug}/structure/chapters/{chapter}/scenes/{scene}', [StructureController::class, 'updateScene'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.structure.scenes.update');
    Route::delete('stories/{story:slug}/structure/chapters/{chapter}/scenes/{scene}', [StructureController::class, 'destroyScene'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.structure.scenes.destroy');
    Route::post('stories/{story:slug}/structure/chapters/{chapter}/scenes/{scene}/beats', [StructureController::class, 'storeBeat'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.structure.beats.store');
    Route::put('stories/{story:slug}/structure/chapters/{chapter}/scenes/{scene}/beats/{beat}', [StructureController::class, 'updateBeat'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.structure.beats.update');
    Route::delete('stories/{story:slug}/structure/chapters/{chapter}/scenes/{scene}/beats/{beat}', [StructureController::class, 'destroyBeat'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.structure.beats.destroy');

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

    // Sessions / saves (E2.1 / S-2.1.1 / S-2.1.2 / S-2.1.3). Start a playthrough
    // by forking a play-ready story into the save realm, then manage the saves
    // forked from it: rename, reset to the freshly-forked state, and delete —
    // each fork independent, the authoring template never mutated (ADR 0012). The
    // nested {playSession} binds through Story::playSessions() via scoped
    // bindings, so a save from another story (or owner) resolves to 404; writes
    // are throttled. Opening Play resumes the save at its persisted loop position
    // (S-2.1.3); the full prose reader is S-5.4.1.
    // Play front door (E0.2 / E0.2.2). The chapter-first entry that hides the
    // fork: entering a book resumes the most-recent playthrough or silently forks
    // one, and entering a specific chapter starts a fresh playthrough there. Both
    // land on the Writing/Play page (E0.4). The {chapter} binds through
    // Story::chapters() via scoped bindings, so a chapter from another story 404s.
    Route::get('stories/{story:slug}/play', [SessionController::class, 'enter'])->name('stories.play');
    Route::get('stories/{story:slug}/chapters/{chapter}/play', [SessionController::class, 'enterChapter'])
        ->scopeBindings()
        ->name('stories.chapters.play');

    Route::get('stories/{story:slug}/saves', [SessionController::class, 'index'])->name('stories.saves.index');
    Route::post('stories/{story:slug}/saves', [SessionController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('stories.saves.store');
    Route::put('stories/{story:slug}/saves/{playSession}', [SessionController::class, 'update'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.saves.update');
    Route::post('stories/{story:slug}/saves/{playSession}/reset', [SessionController::class, 'reset'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.saves.reset');
    Route::delete('stories/{story:slug}/saves/{playSession}', [SessionController::class, 'destroy'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.saves.destroy');
    Route::get('stories/{story:slug}/saves/{playSession}/play', [SessionController::class, 'play'])
        ->scopeBindings()
        ->name('stories.saves.play');
    // Run one narrator turn (E4.2 / S-4.2.1 / S-4.2.2). The prose call produces
    // prose + handoff + elapsed bucket; a validated handoff advances the loop
    // spine, while malformed output is retried then surfaced as a failed turn
    // with the save unchanged. Throttled like the other save writes. The
    // reachable advance control + prose reader land with S-5.4.1.
    Route::post('stories/{story:slug}/saves/{playSession}/narrate', [SessionController::class, 'narrate'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.saves.narrate');
    // Commit the player's contribution at a player moment (S-5.1.1): records the
    // input to the scene log and hands the turn back to the narrator. Throttled
    // like the other save writes.
    Route::post('stories/{story:slug}/saves/{playSession}/input', [SessionController::class, 'input'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.saves.input');
    // Close a finished beat and resume at the next one (S-3.1.2): the player's
    // "continue" at a beat boundary. Throttled like the other save writes.
    Route::post('stories/{story:slug}/saves/{playSession}/continue', [SessionController::class, 'continueBeat'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('stories.saves.continue');

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
