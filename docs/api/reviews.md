# API Contract — Review gate (Sprint 6)

Inertia-props contract for the **shared review gate** (S-6.2.1 / S-6.2.2): the author's queue of engine proposals (`review_items`) with accept / edit / reject decisions. Routes are consumed through the **Wayfinder** typed helpers (`@/routes/reviews`, `@/actions/App/Http/Controllers/Reviews/ReviewController`). All routes require the `auth` middleware.

> The queue is **owner-scoped** (a user only ever sees and acts on their own proposals, enforced by `ReviewItem`'s `OwnerScope` + a nullable `user_id`, PH-20) and the list arrives as a **deferred prop** (`rescue: true`) — the shell renders instantly and the query resolves in a follow-up request behind a skeleton. This is the **foundation**: the decision state machine is built; per-producer commit handlers (writing the committed payload into delta/beat/card tables) land in Phase 7 E4 (PH-27). The UI renders only the proposed payload — a character's private `true_state` is never surfaced.

## 1. Endpoints

| Method | URI | Route name | Auth | Purpose |
|--------|-----|------------|------|---------|
| GET | `/reviews` | `reviews.index` | auth | Render the review gate (`reviews/Index`) |
| POST | `/reviews/{reviewItem}/accept` | `reviews.accept` | auth + `throttle:30,1` | Accept a pending proposal as-is |
| PUT | `/reviews/{reviewItem}` | `reviews.update` | auth + `throttle:30,1` | Commit a proposal with author edits |
| POST | `/reviews/{reviewItem}/reject` | `reviews.reject` | auth + `throttle:30,1` | Reject a pending proposal |

Reached from the top-level **Review** sidebar entry — fully nav-reachable (no URL typing). `{reviewItem}` binds under the owner scope: a foreign proposal resolves to **404**.

## 2. Inertia props (`reviews/Index`)

| Prop | Type | Notes |
|------|------|-------|
| `filter` | string | Active status filter (`all` / `pending` / `accepted` / `edited` / `rejected`); defaults to `pending` |
| `statuses` | `{ value, label }[]` | Filter options (`All` + each `ReviewStatus`) for the segmented control |
| `counts` | `Record<string,int>` | Owner's proposal count per status + `all` total |
| `items` | deferred paginator | `Inertia::defer(…, rescue: true)` — **absent** on the initial response, loaded on a follow-up request (15/page) |

Each `items.data[]` row:

| Field | Type | Notes |
|-------|------|-------|
| `id` | int | Review item id |
| `producerType` / `producerLabel` | string | `ProducerType` value + human label |
| `status` / `statusLabel` | string | `ReviewStatus` value + human label |
| `sessionId` | int \| null | Null for authoring-time proposals |
| `payload` | object | Proposed content |
| `editedPayload` | object \| null | Author edit (when committed via edit) |
| `reviewedAt` | string \| null | ISO-8601 (UTC); rendered in WIB |
| `reviewedBy` | string \| null | Reviewer email (audit) |
| `createdAt` | string \| null | ISO-8601 (UTC); rendered in WIB |

## 3. Validation (`ReviewDecisionRequest`, the `update`/edit route)

| Field | Rules | Notes |
|-------|-------|-------|
| `payload` | `required`, `array` | The edited content to commit; stored in `edited_payload` |

`accept` / `reject` take no body. A decision on an item that is no longer `pending` is caught (`ReviewAlreadyDecidedException`) and surfaced as an "already reviewed" error toast — the recorded who/when is never overwritten.

## 4. States

The page implements the four async states: a **skeleton** while the deferred list loads (`<Deferred>` fallback), an **empty state** ("No proposals to review") teaching what will appear, an **error state** (`<Deferred>` `#rescue` slot + retry via `router.reload({ only: ['items'] })`), and the **proposal list** on success. Reject goes through `useConfirm()` (no native `confirm`); edit opens a Dialog; every decision redirects to `reviews.index` with a flash toast.

## 5. Security

`review_items` is **owner-scoped** (`user_id` + `BelongsToOwner`, PH-20) with a `ReviewItemPolicy extends OwnerPolicy`. The cross-owner negative — a user can neither see nor act on another owner's proposals (a foreign item 404s on binding) — is asserted by `tests/Feature/Reviews/ReviewGateTest.php`. Write routes are throttled (`throttle:30,1`).

## Related

- [../architecture/DATABASE.md](../architecture/DATABASE.md) §4.12 — `review_items` schema · [../adr/0003-relationship-edge-schema.md](../adr/0003-relationship-edge-schema.md) — the review-gate concept
- [../architecture/Diagrams/Engine/Review_Gate_Flow.md](../architecture/Diagrams/Engine/Review_Gate_Flow.md) — propose → decide flow
- [../manual-qa-check/review-gate/S-6-review-gate.md](../manual-qa-check/review-gate/S-6-review-gate.md) — manual QA path
