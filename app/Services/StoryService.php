<?php

namespace App\Services;

use App\Models\Story;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Story lifecycle management (S-1.1.1 / S-1.1.2).
 *
 * Handles create, update, and delete as owner-scoped atomic operations. Slug
 * derivation from title + auto-suffix on collision is centralised here so
 * callers (controller, import, duplicate) all share the same uniqueness logic.
 */
class StoryService
{
    /**
     * Create a new story for the given owner.
     *
     * When slug is omitted the service derives one from the title and
     * auto-suffixes until unique within the owner's stories.
     *
     * @param  User  $owner  The authenticated author.
     * @param  array{title: string, slug?: string|null, description?: string|null}  $data
     *
     * @throws QueryException On constraint violation.
     */
    public function create(User $owner, array $data): Story
    {
        return DB::transaction(function () use ($owner, $data): Story {
            $slug = ! empty($data['slug'])
                ? $data['slug']
                : $this->deriveUniqueSlug($owner->getKey(), $data['title']);

            return Story::create([
                'user_id' => $owner->getKey(),
                'slug' => $slug,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
            ]);
        });
    }

    /**
     * Update an existing story's editable fields.
     *
     * @param  Story  $story  The story to update (already policy-authorized).
     * @param  array{title?: string, slug?: string|null, description?: string|null}  $data
     */
    public function update(Story $story, array $data): Story
    {
        return DB::transaction(function () use ($story, $data): Story {
            $attributes = [];

            if (isset($data['title'])) {
                $attributes['title'] = $data['title'];
            }

            if (isset($data['slug'])) {
                $attributes['slug'] = $data['slug'];
            }

            if (array_key_exists('description', $data)) {
                $attributes['description'] = $data['description'];
            }

            $story->update($attributes);

            return $story->refresh();
        });
    }

    /**
     * Delete a story and cascade to its authoring children.
     *
     * Cascade is handled by the FK `cascadeOnDelete` constraints on all
     * authoring child tables, so the DB handles atomic cleanup.
     *
     * @param  Story  $story  The story to remove (already policy-authorized).
     */
    public function delete(Story $story): void
    {
        DB::transaction(fn () => $story->delete());
    }

    /**
     * Derive a URL-safe slug from a title, auto-suffixing until unique per owner.
     *
     * @param  int  $userId  The owner's id.
     * @param  string  $title  The story title to slugify.
     * @param  int|null  $exceptId  Story id to exclude (for updates).
     */
    public function deriveUniqueSlug(int $userId, string $title, ?int $exceptId = null): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'story';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->slugExistsForOwner($userId, $candidate, $exceptId)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Check whether the slug already exists among the owner's stories.
     */
    private function slugExistsForOwner(int $userId, string $slug, ?int $exceptId = null): bool
    {
        return Story::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }
}
