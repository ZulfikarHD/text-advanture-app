<script setup lang="ts">
/**
 * Stories/Lorebook - the story's lorebook surface (S-3.1.1, ADR 0013 §5).
 *
 * Lists the story's world-fact entries and lets the author create, edit, and
 * delete them through a single responsive dialog. Each entry shows its keywords
 * (the runtime match), a content preview, and any minimum-reveal-chapter gate.
 * Rendered inside the per-story workspace layout (tab nav + story header).
 */
import { Head, router } from '@inertiajs/vue3';
import { BookMarked, Lock, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import LorebookController from '@/actions/App/Http/Controllers/Stories/LorebookController';
import EmptyState from '@/components/EmptyState.vue';
import LorebookEntryDialog from '@/components/stories/LorebookEntryDialog.vue';
import type {LorebookEntry} from '@/components/stories/LorebookEntryDialog.vue';
import type { ChapterOption } from '@/components/stories/LorebookEntryFormFields.vue';
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

type StoryRef = {
    id: number;
    slug: string;
    title: string;
};

const props = defineProps<{
    story: StoryRef;
    entries: LorebookEntry[];
    chapters: ChapterOption[];
}>();

const { confirm } = useConfirm();

const dialogOpen = ref(false);
const activeEntry = ref<LorebookEntry | null>(null);

function openCreate(): void {
    activeEntry.value = null;
    dialogOpen.value = true;
}

function openEdit(entry: LorebookEntry): void {
    activeEntry.value = entry;
    dialogOpen.value = true;
}

async function deleteEntry(entry: LorebookEntry): Promise<void> {
    const confirmed = await confirm({
        title: 'Delete this lorebook entry?',
        description:
            'This permanently removes the entry and its keywords. This cannot be undone.',
        confirmLabel: 'Delete entry',
    });

    if (!confirmed) {
        return;
    }

    router.delete(
        LorebookController.destroy.url({
            story: props.story.slug,
            lorebookEntry: entry.id,
        }),
        { preserveScroll: true },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Workspace', href: dashboard() },
            { title: 'Lorebook', href: '' },
        ],
    },
});
</script>

<template>
    <Head :title="`${props.story.title} · Lorebook`" />

    <!-- Heading + single primary action -->
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="space-y-1">
            <h2 class="text-base font-semibold text-foreground">Lorebook</h2>
            <p class="max-w-prose text-sm text-muted-foreground">
                World facts injected on keyword match at runtime — places,
                objects, and mechanisms, never a character's private interiority.
            </p>
        </div>
        <Button
            v-if="props.entries.length > 0"
            class="h-11 shrink-0"
            data-test="new-lorebook-entry"
            @click="openCreate"
        >
            <Plus class="size-4" />
            New entry
        </Button>
    </header>

    <!-- Empty state: no entries yet -->
    <EmptyState
        v-if="props.entries.length === 0"
        :icon="BookMarked"
        title="No lorebook entries yet"
        description="Add world facts — places, objects, and mechanisms — that the engine injects whenever their keywords appear in a scene."
        data-test="lorebook-empty"
    >
        <template #action>
            <Button
                class="h-11"
                data-test="new-lorebook-entry"
                @click="openCreate"
            >
                <Plus class="size-4" />
                New entry
            </Button>
        </template>
    </EmptyState>

    <!-- Entry list -->
    <div
        v-else
        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        data-test="lorebook-grid"
    >
        <Card
            v-for="entry in props.entries"
            :key="entry.id"
            class="flex flex-col"
            :data-test="`lorebook-entry-${entry.id}`"
        >
            <CardHeader>
                <CardTitle class="line-clamp-1 text-base">
                    {{ entry.title || entry.keywords[0] || 'Untitled entry' }}
                </CardTitle>
                <div class="flex flex-wrap gap-1.5 pt-1">
                    <Badge
                        v-for="keyword in entry.keywords"
                        :key="keyword"
                        variant="secondary"
                    >
                        {{ keyword }}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent class="flex-1">
                <p class="line-clamp-3 text-sm text-muted-foreground">
                    {{ entry.content }}
                </p>
            </CardContent>
            <CardFooter
                class="flex items-center justify-between gap-2 border-t border-border pt-4"
            >
                <Badge
                    v-if="entry.minRevealChapter"
                    variant="outline"
                    class="gap-1"
                >
                    <Lock class="size-3" />
                    Chapter {{ entry.minRevealChapter.number }}
                </Badge>
                <span v-else class="text-xs text-muted-foreground">
                    Always injected
                </span>
                <div class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9"
                        :data-test="`edit-lorebook-${entry.id}`"
                        @click="openEdit(entry)"
                    >
                        <Pencil class="size-4" />
                        <span class="sr-only">Edit entry</span>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9 text-destructive hover:text-destructive"
                        :data-test="`delete-lorebook-${entry.id}`"
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
    <LorebookEntryDialog
        v-model:open="dialogOpen"
        :story-slug="props.story.slug"
        :chapters="props.chapters"
        :entry="activeEntry"
    />
</template>
