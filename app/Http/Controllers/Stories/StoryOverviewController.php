<?php

namespace App\Http\Controllers\Stories;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\StoryOverviewService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Story overview — the authoring inventory + play-readiness view (S-1.2.2).
 *
 * The default surface of a story's workspace. Both the counts and the
 * play-readiness gate are derived on read by {@see StoryOverviewService}; the
 * route binds `{story:slug}` under the owner scope, so a foreign story resolves
 * to 404 without leaking its existence.
 */
class StoryOverviewController extends Controller
{
    public function __construct(private readonly StoryOverviewService $overview) {}

    /**
     * Render a story's overview with derived counts and play-readiness.
     */
    public function show(Story $story): Response
    {
        Gate::authorize('view', $story);

        return Inertia::render('stories/Overview', [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
                'description' => $story->description,
            ],
            'counts' => $this->overview->counts($story),
            'readiness' => $this->overview->readiness($story),
        ]);
    }
}
