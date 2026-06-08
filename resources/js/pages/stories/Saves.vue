<script setup lang="ts">
/**
 * Stories/Saves - the story's save-realm surface (S-2.1.1 / S-2.1.2 / S-2.1.3).
 *
 * Starts a playthrough by forking the play-ready story into the save realm and
 * manages the independent saves forked from it: name on create, rename, reset to
 * the freshly-forked state, and delete (each confirmed). Forking and play never
 * mutate the authoring template (ADR 0012): each save references the immutable
 * structure and evolves on its own, and changes to one never affect another. The
 * "Start session" action is gated on play-readiness (re-checked server-side); a
 * not-yet-ready story routes the author back to the Overview to finish the
 * requirements. Each save opens its Play surface, which resumes at the save's
 * persisted loop position. Rendered inside the per-story workspace layout.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleAlert,
    Pencil,
    Play,
    RotateCcw,
    Save,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import SaveDialog from '@/components/stories/SaveDialog.vue';
import type { RenameableSave } from '@/components/stories/SaveDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { useFormat } from '@/composables/useFormat';
import { dashboard } from '@/routes';
import SessionController from '@/actions/App/Http/Controllers/Stories/SessionController';
import { show as storyShow } from '@/routes/stories';

type StoryRef = {
    id: number;
    slug: string;
    title: string;
};

type Requirement = {
    key: string;
    label: string;
    met: boolean;
    detail: string;
};

type Readiness = {
    ready: boolean;
    requirements: Requirement[];
};

type SaveItem = {
    id: number;
    name: string;
    stateNode: string;
    stateLabel: string;
    lastPlayedAt: string | null;
    resumeAnchor: Record<string, unknown> | null;
    position: {
        chapterNumber: number | null;
        chapterTitle: string | null;
        sceneNumber: number | null;
        beatGoal: string | null;
    };
};

const props = defineProps<{
    story: StoryRef;
    readiness: Readiness;
    saves: SaveItem[];
}>();

const { formatDateTime } = useFormat();
const { confirm } = useConfirm();

const unmetRequirements = computed(() =>
    props.readiness.requirements.filter((req) => !req.met),
);

// Suggested name for the next fork; the dialog prefills it and the server falls
// back to the same default when the field is left blank.
const suggestedName = computed(() => `Playthrough ${props.saves.length + 1}`);

const dialogOpen = ref(false);
const renameTarget = ref<RenameableSave | null>(null);

function openCreate(): void {
    renameTarget.value = null;
    dialogOpen.value = true;
}

function openRename(save: SaveItem): void {
    renameTarget.value = { id: save.id, name: save.name };
    dialogOpen.value = true;
}

async function resetSave(save: SaveItem): Promise<void> {
    const confirmed = await confirm({
        title: `Reset “${save.name}”?`,
        description:
            'This returns the save to its freshly-forked starting position and clears all progress. Other saves and the story template are untouched. This cannot be undone.',
        confirmLabel: 'Reset save',
    });

    if (!confirmed) {
        return;
    }

    router.post(
        SessionController.reset.url({
            story: props.story.slug,
            playSession: save.id,
        }),
        {},
        { preserveScroll: true },
    );
}

async function deleteSave(save: SaveItem): Promise<void> {
    const confirmed = await confirm({
        title: `Delete “${save.name}”?`,
        description:
            'This permanently deletes this playthrough. Other saves and the story template are untouched. This cannot be undone.',
        confirmLabel: 'Delete save',
    });

    if (!confirmed) {
        return;
    }

    router.delete(
        SessionController.destroy.url({
            story: props.story.slug,
            playSession: save.id,
        }),
        { preserveScroll: true },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Workspace', href: dashboard() },
            { title: 'Saves', href: '' },
        ],
    },
});
</script>

<template>
    <Head :title="`${props.story.title} · Saves`" />

    <!-- Heading + single primary action (start a new session) -->
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="space-y-1">
            <h2 class="text-base font-semibold text-foreground">Saves</h2>
            <p class="max-w-prose text-sm text-muted-foreground">
                Each save is an independent playthrough forked from this story's
                authoring template. Starting one never changes the template.
            </p>
        </div>
        <div
            v-if="props.readiness.ready && props.saves.length > 0"
            class="shrink-0"
        >
            <Button class="h-11" data-test="start-session" @click="openCreate">
                <Play class="size-4" />
                Start session
            </Button>
        </div>
    </header>

    <!-- Not play-ready: route back to the overview to finish requirements -->
    <Card v-if="!props.readiness.ready" data-test="saves-not-ready">
        <CardContent class="space-y-4">
            <div class="flex items-start gap-3">
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                >
                    <CircleAlert class="size-5" />
                </span>
                <div class="min-w-0 space-y-1">
                    <h3 class="text-base font-semibold text-foreground">
                        Not yet playable
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        Finish the play-readiness requirements before starting a
                        session.
                    </p>
                </div>
            </div>
            <ul class="space-y-2 border-t border-border pt-4">
                <li
                    v-for="req in unmetRequirements"
                    :key="req.key"
                    class="flex items-start gap-2 text-sm"
                    :data-test="`unmet-${req.key}`"
                >
                    <CircleAlert
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    />
                    <span class="text-muted-foreground">{{ req.detail }}</span>
                </li>
            </ul>
            <Button
                as-child
                variant="outline"
                class="h-11"
                data-test="saves-review-readiness"
            >
                <Link :href="storyShow(props.story.slug)">
                    Review readiness
                    <ArrowRight class="size-4" />
                </Link>
            </Button>
        </CardContent>
    </Card>

    <!-- Ready, but no saves yet -->
    <EmptyState
        v-else-if="props.saves.length === 0"
        :icon="Save"
        title="No saves yet"
        description="Start your first session to fork this story into a save you can play. The authoring template stays untouched."
        data-test="saves-empty"
    >
        <template #action>
            <Button class="h-11" data-test="start-session" @click="openCreate">
                <Play class="size-4" />
                Start session
            </Button>
        </template>
    </EmptyState>

    <!-- Save list (shown whenever saves exist, even if the story drifted out of readiness) -->
    <div
        v-if="props.saves.length > 0"
        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        data-test="saves-grid"
    >
        <Card
            v-for="save in props.saves"
            :key="save.id"
            class="flex flex-col"
            :data-test="`save-${save.id}`"
        >
            <CardContent class="flex h-full flex-col gap-4">
                <div class="flex items-start justify-between gap-2">
                    <h3
                        class="line-clamp-1 text-base font-semibold text-foreground"
                    >
                        {{ save.name }}
                    </h3>
                    <Badge variant="secondary">{{ save.stateLabel }}</Badge>
                </div>
                <div class="flex-1 space-y-1 text-sm">
                    <p class="font-medium text-foreground">
                        Chapter {{ save.position.chapterNumber }}
                        <span v-if="save.position.sceneNumber">
                            · Scene {{ save.position.sceneNumber }}</span
                        >
                    </p>
                    <p
                        v-if="save.position.beatGoal"
                        class="line-clamp-2 text-muted-foreground"
                    >
                        {{ save.position.beatGoal }}
                    </p>
                    <p class="pt-1 text-xs text-muted-foreground">
                        {{
                            save.lastPlayedAt
                                ? `Last played ${formatDateTime(save.lastPlayedAt)}`
                                : 'Not played yet'
                        }}
                    </p>
                </div>

                <!-- Actions: open (load → resume) + manage (rename/reset/delete) -->
                <div
                    class="flex items-center justify-between gap-2 border-t border-border pt-4"
                >
                    <Button
                        as-child
                        variant="outline"
                        class="h-10"
                        :data-test="`open-save-${save.id}`"
                    >
                        <Link
                            :href="
                                SessionController.play.url({
                                    story: props.story.slug,
                                    playSession: save.id,
                                })
                            "
                        >
                            Open
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <div class="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-9"
                            :data-test="`rename-save-${save.id}`"
                            @click="openRename(save)"
                        >
                            <Pencil class="size-4" />
                            <span class="sr-only">Rename save</span>
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-9"
                            :data-test="`reset-save-${save.id}`"
                            @click="resetSave(save)"
                        >
                            <RotateCcw class="size-4" />
                            <span class="sr-only">Reset save</span>
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-9 text-destructive hover:text-destructive"
                            :data-test="`delete-save-${save.id}`"
                            @click="deleteSave(save)"
                        >
                            <Trash2 class="size-4" />
                            <span class="sr-only">Delete save</span>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Create / rename dialog (single instance serves both modes) -->
    <SaveDialog
        v-model:open="dialogOpen"
        :story-slug="props.story.slug"
        :save="renameTarget"
        :suggested-name="suggestedName"
    />
</template>
