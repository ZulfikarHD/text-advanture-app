# Structure API Contract (S-1.2.1)

> Per-story **minimal manual** structure CRUD inside the authoring workspace — the hand-authored **chapter → scene → beat** the loop plays through, with **no LLM call, no API key**. A chapter is `{ title, pov_default }`; a scene is `{ pov_mode, pov_anchor, tone?, present_characters }` (its POV contract); a beat is `{ goal }`. The per-parent `number` is system-managed (`max + 1`, locked). All endpoints are auth-gated and owner-scoped; the nested chapter/scene/beat bind down the `{story}→{chapter}→{scene}→{beat}` chain via scoped bindings. Governed by ADR 0015 (minimal slice); the full beat document (`intent`/`word_budget`/`nudge_target`) and outline compilation are later (Phase 4 / O6, PH-35).

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/stories/{story:slug}/structure` | `stories.structure.index` | `StructureController@index` |
| `POST` | `/stories/{story:slug}/structure/chapters` | `stories.structure.chapters.store` | `StructureController@storeChapter` |
| `PUT` | `/stories/{story:slug}/structure/chapters/{chapter}` | `stories.structure.chapters.update` | `StructureController@updateChapter` |
| `DELETE` | `/stories/{story:slug}/structure/chapters/{chapter}` | `stories.structure.chapters.destroy` | `StructureController@destroyChapter` |
| `POST` | `/stories/{story:slug}/structure/chapters/{chapter}/scenes` | `stories.structure.scenes.store` | `StructureController@storeScene` |
| `PUT` | `/stories/{story:slug}/structure/chapters/{chapter}/scenes/{scene}` | `stories.structure.scenes.update` | `StructureController@updateScene` |
| `DELETE` | `/stories/{story:slug}/structure/chapters/{chapter}/scenes/{scene}` | `stories.structure.scenes.destroy` | `StructureController@destroyScene` |
| `POST` | `.../scenes/{scene}/beats` | `stories.structure.beats.store` | `StructureController@storeBeat` |
| `PUT` | `.../scenes/{scene}/beats/{beat}` | `stories.structure.beats.update` | `StructureController@updateBeat` |
| `DELETE` | `.../scenes/{scene}/beats/{beat}` | `stories.structure.beats.destroy` | `StructureController@destroyBeat` |

- `{story:slug}` resolves under the `OwnerScope` global scope — a foreign story is **404**, never leaked.
- Every nested child resolves via **scoped bindings** (`->scopeBindings()`): each must belong to its bound parent (`Story::chapters()` → `Chapter::scenes()` → `Scene::beats()`), so a row whose ancestor chain doesn't match the URL is **404**.
- Write routes are throttled (`throttle:30,1`).

## Inertia props

### `stories/Structure` (index)

| Prop | Type | Notes |
|------|------|-------|
| `story` | `StoryRef` | The owner-scoped story whose workspace is open |
| `characters` | `CharacterRef[]` | This story's cast (players first, then by name) for the present-cast + anchor selects |
| `chapters` | `StructureChapter[]` | The nested chapter → scene → beat tree, ordered by `number` |
| `povOptions` | `PovOption[]` | The `PovMode` vocabulary (value + label + description) |
| `defaultPov` | `string` | The story's resolved default POV, preselected when creating |

```typescript
type StoryRef = { id: number; slug: string; title: string };
type CharacterRef = { id: number; slug: string; name: string; isPlayer: boolean };
type PovOption = { value: string; label: string; description: string };

type StructureBeat = { id: number; number: number; goal: string };

type StructureScene = {
    id: number;
    number: number;
    povMode: string;
    povAnchor: string;          // a character slug
    tone: string | null;
    presentCharacters: string[]; // character slugs
    beats: StructureBeat[];
};

type StructureChapter = {
    id: number;
    number: number;
    title: string;
    povDefault: string;
    canDelete: boolean;          // false while it anchors character cards
    scenes: StructureScene[];
};
```

## Request bodies

### Chapter (`Store`/`Update`ChapterRequest)

| Field | Type | Rules |
|-------|------|-------|
| `title` | `string` | required, max 200 |
| `pov_default` | `string` | required, `PovMode` enum (`first_person` / `second_person` / `third_limited` / `third_omniscient`) |

`number` is **not accepted** — it is system-managed (`max(number) + 1` per story).

### Scene (`Store`/`Update`SceneRequest)

| Field | Type | Rules |
|-------|------|-------|
| `pov_mode` | `string` | required, `PovMode` enum |
| `pov_anchor` | `string` | required, max 150; must be a story character slug **and** ∈ `present_characters` |
| `tone` | `string?` | nullable, max 120 |
| `present_characters` | `string[]` | required, ≥ 1 item, max 150 each; every slug must belong to the story |

- The present-cast / anchor cross-checks run in the request `after()` hook (reading `$this->route('story')`).
- On create, the service sets `elapsed_bucket = Continuous` and `elapsed_source = Default` (declaring an in-world gap is deferred, PH-35).

### Beat (`Store`/`Update`BeatRequest)

| Field | Type | Rules |
|-------|------|-------|
| `goal` | `string` | required, max 255 |

On create, the service writes the deferred beat-document defaults `intent = ''` and `word_budget = StructureService::DEFAULT_WORD_BUDGET` (500) to satisfy their `NOT NULL` columns (PH-35).

## Validation messages

| Rule | Message |
|------|---------|
| `title.required` | "Give the chapter a title." |
| `pov_default.required` | "Choose the chapter's default point of view." |
| `pov_mode.required` | "Choose how the scene is narrated." |
| `pov_anchor.required` | "Choose the scene's viewpoint character." |
| `present_characters.required` / `.min` | "Add at least one character present in the scene." |
| `present_characters` (synthetic) | "Every present character must belong to this story." |
| `pov_anchor` (synthetic, not a story char) | "The viewpoint character must belong to this story." |
| `pov_anchor` (synthetic, not present) | "The viewpoint character must be one of the present characters." |
| `goal.required` | "A goal is required — it is the beat's satisfaction anchor." |

## Flash / toast

All mutating endpoints flash `Inertia::flash('toast', ...)` and redirect to `stories.structure.index`:

| Action | Type | Message |
|--------|------|---------|
| Create chapter / scene / beat | `success` | "Chapter created." / "Scene created." / "Beat created." |
| Update chapter / scene / beat | `success` | "Chapter updated." / "Scene updated." / "Beat updated." |
| Delete chapter / scene / beat | `success` | "Chapter deleted." / "Scene deleted." / "Beat deleted." |
| Delete chapter with character cards | `error` | "This chapter anchors character cards — move or delete those characters first." |

## Ownership & authorization

- `StoryPolicy` (extending `OwnerPolicy`) gates by ownership: `view` for index, `update` for all writes.
- Structure rows carry no `user_id`; they inherit isolation transitively through their story, so there is no row-level policy — the parent gate + nested scoped bindings are the enforcement.
- **Chapter-delete guard.** A chapter that still anchors `character_cards` cannot be deleted (it would orphan the E1.1 cast); the request is rejected with an error toast and no delete runs.

## Out of scope

- **Beat document** (`intent`, `word_budget`, `nudge_target`), in-world elapsed-time authoring, and **outline compilation** land in Phase 4 (O6); only `goal` is authored here (PH-35).
- **AI / hybrid authoring** of structure is later; E1.2 is the minimal manual slice only.
- Slug-stored `pov_anchor` / `present_characters` carry **no FK** (validated at request time); the runtime POV / scene-state consumers (E4) are separate and later.
