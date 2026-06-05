<?php

namespace App\Http\Controllers\Reviews;

use App\Enums\ReviewStatus;
use App\Exceptions\Review\ReviewAlreadyDecidedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\ReviewDecisionRequest;
use App\Models\ReviewItem;
use App\Services\ReviewGateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shared review-gate surface (S-6.2.1, S-6.2.2, ADR 0003).
 *
 * Lists the owner's pending/decided proposals and applies accept / edit /
 * reject decisions through {@see ReviewGateService}. Everything is owner-scoped:
 * the counts query and the deferred list run under the owner global scope, and
 * decision routes bind the item under it too, so a foreign proposal resolves to
 * a 404 and never leaks. The list is a deferred prop so the shell renders
 * immediately behind a skeleton.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewGateService $gate) {}

    /**
     * Render the review-gate shell; the filtered item list loads as a deferred prop.
     */
    public function index(Request $request): Response
    {
        $filter = $this->resolveFilter($request->string('status')->value());

        return Inertia::render('reviews/Index', [
            'filter' => $filter,
            'statuses' => $this->statusOptions(),
            'counts' => $this->ownerCounts(),
            // rescue: true so a failed list query renders the page's #rescue
            // state instead of a hard error (one of the four required states).
            'items' => Inertia::defer(fn (): array => $this->ownerItems($filter), rescue: true),
        ]);
    }

    /**
     * Accept a pending proposal as-is.
     */
    public function accept(Request $request, ReviewItem $reviewItem): RedirectResponse
    {
        return $this->decide(fn () => $this->gate->accept($reviewItem, $request->user()), __('Proposal accepted.'));
    }

    /**
     * Commit a pending proposal with the author's edits.
     */
    public function update(ReviewDecisionRequest $request, ReviewItem $reviewItem): RedirectResponse
    {
        return $this->decide(
            fn () => $this->gate->edit($reviewItem, $request->validated('payload'), $request->user()),
            __('Edited proposal committed.'),
        );
    }

    /**
     * Reject a pending proposal; nothing is committed.
     */
    public function reject(Request $request, ReviewItem $reviewItem): RedirectResponse
    {
        return $this->decide(fn () => $this->gate->reject($reviewItem, $request->user()), __('Proposal rejected.'));
    }

    /**
     * Run a decision and surface a success or "already reviewed" toast.
     *
     * @param  callable(): ReviewItem  $decision
     */
    private function decide(callable $decision, string $successMessage): RedirectResponse
    {
        try {
            $decision();
            Inertia::flash('toast', ['type' => 'success', 'message' => $successMessage]);
        } catch (ReviewAlreadyDecidedException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('That proposal was already reviewed.')]);
        }

        return to_route('reviews.index');
    }

    /**
     * The owner's proposal counts per status, plus a total.
     *
     * @return array<string, int>
     */
    private function ownerCounts(): array
    {
        $byStatus = ReviewItem::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = ['all' => (int) $byStatus->sum()];

        foreach (ReviewStatus::cases() as $status) {
            $counts[$status->value] = (int) $byStatus->get($status->value, 0);
        }

        return $counts;
    }

    /**
     * The owner-scoped, filtered, paginated, presentable proposal list.
     *
     * @return array<string, mixed> The paginator array (`data` + pagination meta).
     */
    private function ownerItems(string $filter): array
    {
        return ReviewItem::query()
            ->when(
                $filter !== 'all',
                fn ($query) => $query->where('status', $filter),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ReviewItem $item): array => [
                'id' => $item->id,
                'producerType' => $item->producer_type->value,
                'producerLabel' => $item->producer_type->label(),
                'status' => $item->status->value,
                'statusLabel' => $item->status->label(),
                'sessionId' => $item->session_id,
                'payload' => $item->payload,
                'editedPayload' => $item->edited_payload,
                'reviewedAt' => $item->reviewed_at?->toIso8601String(),
                'reviewedBy' => $item->reviewed_by,
                'createdAt' => $item->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * The valid list filters: every status plus an "all" view.
     *
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        $options = [['value' => 'all', 'label' => __('All')]];

        foreach (ReviewStatus::cases() as $status) {
            $options[] = ['value' => $status->value, 'label' => $status->label()];
        }

        return $options;
    }

    /**
     * Coerce the requested status filter to a known value (default: pending).
     */
    private function resolveFilter(string $status): string
    {
        $valid = array_merge(['all'], array_column(ReviewStatus::cases(), 'value'));

        return in_array($status, $valid, true) ? $status : ReviewStatus::Pending->value;
    }
}
