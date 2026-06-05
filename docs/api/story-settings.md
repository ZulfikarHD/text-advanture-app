# Story Settings API Contract (S-1.2.1)

> Per-story configuration: default POV + per-role model overrides. Auth-gated and owner-scoped (a foreign story 404s on route-model binding).

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/stories/{story:slug}/settings` | `stories.settings.edit` | `StorySettingsController@edit` |
| `PUT` | `/stories/{story:slug}/settings` | `stories.settings.update` | `StorySettingsController@update` (throttled `12,1`) |

## Inertia props

### `stories/Settings`

| Prop | Type | Notes |
|------|------|-------|
| `story` | `StoryRef` | `{ id, slug, title }` — also drives the workspace sub-nav |
| `defaultPov` | `string` | Current default POV (resolved value; enum default when unset) |
| `povOptions` | `PovOption[]` | Selectable POV modes |
| `roles` | `RoleRow[]` | One row per engine `LlmRole`, merging the global default with any story override |

```typescript
type PovOption = { value: string; label: string; description: string };

type RoleRow = {
    role: string;          // LlmRole value
    label: string;
    description: string;
    override: boolean;     // true when a story-scoped profile exists
    modelSlug: string;     // override value, else global prefill
    temperature: number;
    maxTokens: number;
    isActive: boolean;
    global: {              // fallback shown when override is off
        modelSlug: string;
        temperature: number;
        maxTokens: number;
        isActive: boolean;
        configured: boolean;
    };
};
```

## Request body — `PUT` (UpdateStorySettingsRequest)

| Field | Type | Rules |
|-------|------|-------|
| `default_pov` | `string` | required, enum `PovMode` (`first_person`, `second_person`, `third_limited`, `third_omniscient`) |
| `roles` | `array` | required, min 1 |
| `roles.*.role` | `string` | required, enum `LlmRole` |
| `roles.*.override` | `bool` | required |
| `roles.*.model_slug` | `string?` | `required_if` override true, max 120 |
| `roles.*.temperature` | `number?` | `required_if` override true, 0–2 |
| `roles.*.max_tokens` | `int?` | `required_if` override true, 1–200000 |
| `roles.*.is_active` | `bool?` | nullable |

## Persistence

- `default_pov` is written into `stories.settings` JSON (`settings.default_pov`).
- Per role, when `override` is **true** the service `updateOrCreate`s a
  `model_profiles` row (`scope=story`, `story_id`, `role`); when **false** it
  **deletes** that row so the role falls back to the global default.
- The whole save runs inside a single `DB::transaction`.

## Resolution order

`ModelRoleResolver::resolve(role, story)` prefers the story-scoped profile, then
the global default; an inactive or missing profile fails closed
(`UnresolvedModelRoleException`). POV resolves to `PovMode::default()`
(`third_limited`) when `settings.default_pov` is unset.

## Flash / toast

| Action | Type | Message |
|--------|------|---------|
| Update | `success` | "Story settings saved." |

## Ownership & authorization

- Route-model binding resolves `{story:slug}` under `OwnerScope`; foreign stories 404.
- `Gate::authorize('view' | 'update', $story)` (via `StoryPolicy`) gates each action.

## Out of scope (deferred)

Per-story **rubric / elapsed / drift** tunable overrides are deferred to E5.1
(PH-29) — they need a global rubric config home first (PH-8). Settings ships POV
+ model-role overrides only.

## Related

- [model-roles.md](./model-roles.md) (global defaults this overrides) · [stories.md](./stories.md) · [story-overview.md](./story-overview.md)
- [../architecture/Diagrams/Authoring/Story_Settings_Overview_Flow.md](../architecture/Diagrams/Authoring/Story_Settings_Overview_Flow.md)
- [../architecture/DATABASE.md](../architecture/DATABASE.md) §3.1 (`stories.settings`) · §3.16 (`model_profiles`)
