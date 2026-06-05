<script setup lang="ts">
/**
 * Reviews/Index - the shared review-gate workspace (S-6.2.1, S-6.2.2).
 *
 * Where the author accepts, edits, or rejects engine proposals. This is the
 * Phase 1 foundation: the queue + decision controls. Producers do not exist yet,
 * so the common state is the empty teaching state. Everything is owner-scoped on
 * the server; this view never renders another author's proposals, and it never
 * surfaces a character's private true_state (only the proposed payload the gate
 * exists to review).
 */
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { ClipboardCheck } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { useConfirm } from '@/composables/useConfirm';
import { useFormat } from '@/composables/useFormat';
import reviews from '@/routes/reviews';

type ReviewRow = {
    id: number;
    producerType: string;
    producerLabel: string;
    status: string;
    statusLabel: string;
    sessionId: number | null;
    payload: Record<string, unknown>;
    editedPayload: Record<string, unknown> | null;
    reviewedAt: string | null;
    reviewedBy: string | null;
    createdAt: string | null;
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    from: number | null;
    to: number | null;
    total: number;
};

type StatusOption = { value: string; label: string };

type Props = {
    filter: string;
    statuses: StatusOption[];
    counts: Record<string, number>;
    items?: Paginated<ReviewRow>;
};

defineProps<Props>();

const { formatDateTime } = useFormat();
const { confirm } = useConfirm();

// Maps a decision state to its badge tone (Von Restorff - rejected stands out).
const STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    pending: 'secondary',
    accepted: 'default',
    edited: 'outline',
    rejected: 'destructive',
};

const processingId = ref<number | null>(null);

// Edit-dialog state: the JSON payload is edited as text and parsed on commit.
const editing = ref<ReviewRow | null>(null);
const editText = ref('');
const editError = ref('');
const editProcessing = ref(false);

function statusVariant(status: string): 'default' | 'secondary' | 'outline' | 'destructive' {
    return STATUS_VARIANTS[status] ?? 'secondary';
}

function pretty(value: unknown): string {
    return JSON.stringify(value, null, 2);
}

function filterUrl(status: string): string {
    return reviews.index.url({ query: { status } });
}

function accept(item: ReviewRow): void {
    processingId.value = item.id;
    router.post(
        reviews.accept.url(item.id),
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

async function reject(item: ReviewRow): Promise<void> {
    const confirmed = await confirm({
        title: 'Reject this proposal?',
        description: 'The proposal will be marked rejected and nothing will be committed. This cannot be undone.',
        confirmLabel: 'Reject',
    });

    if (!confirmed) {
        return;
    }

    processingId.value = item.id;
    router.post(
        reviews.reject.url(item.id),
        {},
        { preserveScroll: true, onFinish: () => (processingId.value = null) },
    );
}

function openEdit(item: ReviewRow): void {
    editing.value = item;
    editText.value = pretty(item.editedPayload ?? item.payload);
    editError.value = '';
}

function closeEdit(open: boolean): void {
    if (!open) {
        editing.value = null;
    }
}

function commitEdit(): void {
    if (!editing.value) {
        return;
    }

    let parsed: unknown;

    try {
        parsed = JSON.parse(editText.value);
    } catch {
        editError.value = 'Enter valid JSON before committing.';

        return;
    }

    if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
        editError.value = 'The edited content must be a JSON object.';

        return;
    }

    editProcessing.value = true;
    router.put(
        reviews.update.url(editing.value.id),
        { payload: parsed as Record<string, unknown> },
        {
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
            onError: (errors) => (editError.value = errors.payload ?? 'Could not commit the edit.'),
            onFinish: () => (editProcessing.value = false),
        },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Review', href: reviews.index() }],
    },
});
</script>

<template>
    <Head title="Review" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Page heading -->
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                Review gate
            </h1>
            <p class="text-sm text-muted-foreground">
                Accept, edit, or reject the proposals the engine sends here before
                they are committed to your story.
            </p>
        </header>

        <!-- Status filter (segmented). Counts are global; the list re-defers. -->
        <nav class="flex flex-wrap gap-2" aria-label="Filter proposals by status">
            <Button
                v-for="option in statuses"
                :key="option.value"
                as-child
                :variant="filter === option.value ? 'default' : 'outline'"
                class="h-11"
                :data-test="`review-filter-${option.value}`"
            >
                <Link
                    :href="filterUrl(option.value)"
                    :only="['items', 'filter']"
                    preserve-scroll
                    preserve-state
                >
                    {{ option.label }}
                    <Badge
                        v-if="counts[option.value] !== undefined"
                        variant="secondary"
                        class="ml-2"
                    >
                        {{ counts[option.value] }}
                    </Badge>
                </Link>
            </Button>
        </nav>

        <!-- The proposal list loads as a deferred prop behind a skeleton. -->
        <Deferred data="items">
            <template #fallback>
                <div class="space-y-3" data-test="review-loading">
                    <Skeleton v-for="n in 3" :key="n" class="h-28 w-full rounded-xl" />
                </div>
            </template>

            <!-- Error: the list query failed; offer a retry. -->
            <template #rescue>
                <ErrorState
                    title="We couldn't load your proposals"
                    message="Something went wrong fetching the review queue. Please try again."
                >
                    <template #action>
                        <Button class="h-11" @click="router.reload({ only: ['items'] })">
                            Retry
                        </Button>
                    </template>
                </ErrorState>
            </template>

            <!-- Empty: nothing in this filter. Teach what will appear here. -->
            <EmptyState
                v-if="!items || items.data.length === 0"
                :icon="ClipboardCheck"
                title="No proposals to review"
                description="When the engine generates changes - relationship deltas, compiled cards, beat records - they will appear here for you to accept, edit, or reject. Nothing is waiting right now."
                data-test="review-empty"
            />

            <!-- Success: the proposal list. -->
            <div v-else class="space-y-4" data-test="review-list">
                <article
                    v-for="item in items.data"
                    :key="item.id"
                    class="space-y-4 rounded-xl border border-border bg-card/40 p-4"
                    :data-test="`review-item-${item.id}`"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-medium text-foreground">
                                {{ item.producerLabel }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Proposed {{ formatDateTime(item.createdAt) }}
                                <span v-if="item.sessionId">· session #{{ item.sessionId }}</span>
                                <span v-else>· authoring</span>
                            </p>
                        </div>
                        <Badge :variant="statusVariant(item.status)">
                            {{ item.statusLabel }}
                        </Badge>
                    </div>

                    <!-- Proposed payload (and the committed edit, if any). -->
                    <div class="space-y-2">
                        <p class="text-xs font-medium text-muted-foreground">Proposed payload</p>
                        <pre
                            class="max-h-60 overflow-auto rounded-lg border border-border bg-muted/40 p-3 font-mono text-xs text-foreground"
                        >{{ pretty(item.editedPayload ?? item.payload) }}</pre>
                    </div>

                    <!-- Decision controls for a pending item (one primary: Accept). -->
                    <div
                        v-if="item.status === 'pending'"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <Button
                            class="h-11"
                            :disabled="processingId === item.id"
                            :data-test="`review-accept-${item.id}`"
                            @click="accept(item)"
                        >
                            Accept
                        </Button>
                        <Button
                            variant="outline"
                            class="h-11"
                            :disabled="processingId === item.id"
                            :data-test="`review-edit-${item.id}`"
                            @click="openEdit(item)"
                        >
                            Edit
                        </Button>
                        <Button
                            variant="ghost"
                            class="h-11 text-destructive hover:text-destructive"
                            :disabled="processingId === item.id"
                            :data-test="`review-reject-${item.id}`"
                            @click="reject(item)"
                        >
                            Reject
                        </Button>
                    </div>

                    <!-- A decided item shows who reviewed it and when. -->
                    <p v-else class="text-xs text-muted-foreground">
                        {{ item.statusLabel }}
                        <template v-if="item.reviewedBy">by {{ item.reviewedBy }}</template>
                        <template v-if="item.reviewedAt">· {{ formatDateTime(item.reviewedAt) }}</template>
                    </p>
                </article>

                <!-- Pagination (mirrors the usage log). -->
                <div
                    v-if="items.prev_page_url || items.next_page_url"
                    class="flex items-center justify-between pt-2"
                >
                    <p class="text-xs text-muted-foreground">
                        Showing {{ items.from ?? 0 }}–{{ items.to ?? 0 }} of {{ items.total }}
                    </p>
                    <div class="flex gap-2">
                        <Button
                            v-if="items.prev_page_url"
                            as-child
                            variant="outline"
                            class="h-11"
                        >
                            <Link :href="items.prev_page_url" :only="['items']" preserve-scroll>
                                Previous
                            </Link>
                        </Button>
                        <Button
                            v-if="items.next_page_url"
                            as-child
                            variant="outline"
                            class="h-11"
                        >
                            <Link :href="items.next_page_url" :only="['items']" preserve-scroll>
                                Next
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </Deferred>
    </div>

    <!-- Edit-and-commit dialog (desktop). Reject/destructive uses useConfirm. -->
    <Dialog :open="editing !== null" @update:open="closeEdit">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Edit proposal</DialogTitle>
                <DialogDescription>
                    Adjust the proposed payload as JSON. Committing records your edit
                    separately from the original proposal.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="review-edit-payload">Payload (JSON)</Label>
                <textarea
                    id="review-edit-payload"
                    v-model="editText"
                    rows="12"
                    spellcheck="false"
                    class="min-h-44 w-full rounded-lg border border-border bg-background p-3 font-mono text-xs text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                    data-test="review-edit-textarea"
                ></textarea>
                <p v-if="editError" class="text-sm text-destructive" data-test="review-edit-error">
                    {{ editError }}
                </p>
            </div>

            <DialogFooter>
                <Button variant="outline" class="h-11" @click="closeEdit(false)">
                    Cancel
                </Button>
                <Button
                    class="h-11"
                    :disabled="editProcessing"
                    data-test="review-edit-commit"
                    @click="commitEdit"
                >
                    Commit edit
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
