<script setup lang="ts">
/**
 * Stories/RevealLedger - the story's reveal-ledger surface (S-4.1.1, ADR 0013 §3).
 *
 * Lists the story's load-bearing secrets and lets the author create, edit, and
 * delete them through a single responsive dialog. Each entry shows its fact, the
 * chapter it is revealed at, who it is about (or "world secret"), and the
 * pre-reveal knowers exempt from the clamp. Because a reveal point is required
 * and chapters land in a later phase, creation is gated behind a teaching empty
 * state until the story has at least one chapter. Rendered inside the per-story
 * workspace layout (tab nav + story header).
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    KeyRound,
    Lock,
    Pencil,
    Plus,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import RevealLedgerEntryDialog from '@/components/stories/RevealLedgerEntryDialog.vue';
import type { RevealLedgerEntry } from '@/components/stories/RevealLedgerEntryDialog.vue';
import type {
    CharacterOption,
    ChapterOption,
} from '@/components/stories/RevealLedgerEntryFormFields.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { dashboard } from '@/routes';
import RevealLedgerController from '@/actions/App/Http/Controllers/Stories/RevealLedgerController';
import { index as structureIndex } from '@/routes/stories/structure';

type StoryRef = {
    id: number;
    slug: string;
    title: string;
};

const props = defineProps<{
    story: StoryRef;
    entries: RevealLedgerEntry[];
    chapters: ChapterOption[];
    characters: CharacterOption[];
}>();

const { confirm } = useConfirm();

const dialogOpen = ref(false);
const activeEntry = ref<RevealLedgerEntry | null>(null);

// A reveal point is required, so an entry can only be created once the story has
// at least one chapter (chapters are authored in a later phase).
const hasChapters = computed(() => props.chapters.length > 0);

function openCreate(): void {
    activeEntry.value = null;
    dialogOpen.value = true;
}

function openEdit(entry: RevealLedgerEntry): void {
    activeEntry.value = entry;
    dialogOpen.value = true;
}

async function deleteEntry(entry: RevealLedgerEntry): Promise<void> {
    const confirmed = await confirm({
        title: 'Delete this reveal-ledger entry?',
        description:
            'This permanently removes the secret and its reveal point. This cannot be undone.',
        confirmLabel: 'Delete entry',
    });

    if (!confirmed) {
        return;
    }

    router.delete(
        RevealLedgerController.destroy.url({
            story: props.story.slug,
            revealLedgerEntry: entry.id,
        }),
        { preserveScroll: true },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Workspace', href: dashboard() },
            { title: 'Reveal ledger', href: '' },
        ],
    },
});
</script>

<template>
    <Head :title="`${props.story.title} · Reveal ledger`" />

    <!-- Heading + single primary action -->
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="space-y-1">
            <h2 class="text-base font-semibold text-foreground">Reveal ledger</h2>
            <p class="max-w-prose text-sm text-muted-foreground">
                Load-bearing secrets and the chapter each becomes known — so
                spoiler-safety is explicit and never rests on inference.
            </p>
        </div>
        <div v-if="props.entries.length > 0 && hasChapters" class="shrink-0">
            <Button class="h-11" data-test="new-reveal-entry" @click="openCreate">
                <Plus class="size-4" />
                New entry
            </Button>
        </div>
    </header>

    <!-- Empty state: no chapters yet (a reveal point is required) -->
    <EmptyState
        v-if="props.entries.length === 0 && !hasChapters"
        :icon="BookOpen"
        title="Add a chapter first"
        description="A reveal-ledger entry needs a chapter to reveal at. Author the story's structure, then come back to record its load-bearing secrets."
        data-test="reveal-needs-chapter"
    >
        <template #action>
            <Button as-child class="h-11">
                <Link :href="structureIndex(props.story.slug)">
                    <BookOpen class="size-4" />
                    Go to Structure
                </Link>
            </Button>
        </template>
    </EmptyState>

    <!-- Empty state: chapters exist but no entries yet -->
    <EmptyState
        v-else-if="props.entries.length === 0"
        :icon="KeyRound"
        title="No reveal-ledger entries yet"
        description="Record the few critical secrets that must not leak early — each with the chapter it is revealed at and anyone who knows it before then."
        data-test="reveal-empty"
    >
        <template #action>
            <Button class="h-11" data-test="new-reveal-entry" @click="openCreate">
                <Plus class="size-4" />
                New entry
            </Button>
        </template>
    </EmptyState>

    <!-- Entry list -->
    <div
        v-else
        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        data-test="reveal-grid"
    >
        <Card
            v-for="entry in props.entries"
            :key="entry.id"
            class="flex flex-col"
            :data-test="`reveal-entry-${entry.id}`"
        >
            <CardHeader>
                <CardTitle class="line-clamp-1 font-mono text-sm">
                    {{ entry.fact }}
                </CardTitle>
                <div class="flex flex-wrap gap-1.5 pt-1">
                    <Badge v-if="entry.character" variant="secondary" class="gap-1">
                        <Users class="size-3" />
                        {{ entry.character.name }}
                    </Badge>
                    <Badge v-else variant="outline">World secret</Badge>
                </div>
            </CardHeader>
            <CardContent class="flex-1 space-y-3">
                <div v-if="entry.whoKnows.length > 0" class="space-y-1.5">
                    <p class="text-xs font-medium text-muted-foreground">
                        Knows before reveal
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <Badge
                            v-for="slug in entry.whoKnows"
                            :key="slug"
                            variant="secondary"
                            class="font-mono"
                        >
                            {{ slug }}
                        </Badge>
                    </div>
                </div>
                <p
                    v-if="entry.notes"
                    class="line-clamp-3 text-sm text-muted-foreground"
                >
                    {{ entry.notes }}
                </p>
            </CardContent>
            <CardFooter
                class="flex items-center justify-between gap-2 border-t border-border pt-4"
            >
                <Badge variant="outline" class="gap-1">
                    <Lock class="size-3" />
                    Chapter {{ entry.revealChapter.number }}
                </Badge>
                <div class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9"
                        :data-test="`edit-reveal-${entry.id}`"
                        @click="openEdit(entry)"
                    >
                        <Pencil class="size-4" />
                        <span class="sr-only">Edit entry</span>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9 text-destructive hover:text-destructive"
                        :data-test="`delete-reveal-${entry.id}`"
                        @click="deleteEntry(entry)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Delete entry</span>
                    </Button>
                </div>
            </CardFooter>
        </Card>
    </div>

    <!-- Create / edit dialog (single instance serves both modes) -->
    <RevealLedgerEntryDialog
        v-model:open="dialogOpen"
        :story-slug="props.story.slug"
        :chapters="props.chapters"
        :characters="props.characters"
        :entry="activeEntry"
    />
</template>
