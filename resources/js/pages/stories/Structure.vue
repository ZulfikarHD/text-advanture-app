<script setup lang="ts">
/**
 * Stories/Structure - the story's structure surface (S-1.2.1).
 *
 * Authors the chapter -> scene -> beat hierarchy by hand — no model call — so the
 * loop has a position and an anchor to narrate toward. Chapters list their
 * scenes; each scene shows its POV contract + present cast and lists its beats;
 * each beat shows its goal. Create / edit / delete run through per-level dialogs.
 * Scenes need at least one character (the viewpoint anchor), so the surface
 * steers the author to the Characters tab when the cast is empty. Rendered inside
 * the per-story workspace layout (tab nav + header).
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BookMarked,
    Clapperboard,
    Eye,
    ListTree,
    Pencil,
    Plus,
    Target,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import BeatDialog from '@/components/stories/structure/BeatDialog.vue';
import ChapterDialog from '@/components/stories/structure/ChapterDialog.vue';
import SceneDialog from '@/components/stories/structure/SceneDialog.vue';
import type {
    CharacterRef,
    PovOption,
    StructureBeat,
    StructureChapter,
    StructureScene,
} from '@/components/stories/structure/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { dashboard } from '@/routes';
import StructureController from '@/actions/App/Http/Controllers/Stories/StructureController';
import { index as charactersIndex } from '@/routes/stories/characters';

type StoryRef = {
    id: number;
    slug: string;
    title: string;
};

const props = defineProps<{
    story: StoryRef;
    characters: CharacterRef[];
    chapters: StructureChapter[];
    povOptions: PovOption[];
    defaultPov: string;
}>();

const { confirm } = useConfirm();

// Scenes need a viewpoint character, so authoring is gated on a non-empty cast.
const hasCharacters = computed(() => props.characters.length > 0);

// Resolve a character slug to its display name for the present-cast / anchor
// badges (slugs are what the scene stores).
const nameBySlug = computed<Record<string, string>>(() =>
    Object.fromEntries(props.characters.map((c) => [c.slug, c.name])),
);

function characterName(slug: string): string {
    return nameBySlug.value[slug] ?? slug;
}

const povLabels = computed<Record<string, string>>(() =>
    Object.fromEntries(props.povOptions.map((p) => [p.value, p.label])),
);

function povLabel(value: string): string {
    return povLabels.value[value] ?? value;
}

// --- Dialog state (one instance per level serves both create and edit) ---

const chapterDialogOpen = ref(false);
const activeChapter = ref<StructureChapter | null>(null);

const sceneDialogOpen = ref(false);
const activeScene = ref<StructureScene | null>(null);
const sceneChapterId = ref<number | null>(null);

const beatDialogOpen = ref(false);
const activeBeat = ref<StructureBeat | null>(null);
const beatChapterId = ref<number | null>(null);
const beatSceneId = ref<number | null>(null);

function openCreateChapter(): void {
    activeChapter.value = null;
    chapterDialogOpen.value = true;
}

function openEditChapter(chapter: StructureChapter): void {
    activeChapter.value = chapter;
    chapterDialogOpen.value = true;
}

function openCreateScene(chapterId: number): void {
    activeScene.value = null;
    sceneChapterId.value = chapterId;
    sceneDialogOpen.value = true;
}

function openEditScene(chapterId: number, scene: StructureScene): void {
    activeScene.value = scene;
    sceneChapterId.value = chapterId;
    sceneDialogOpen.value = true;
}

function openCreateBeat(chapterId: number, sceneId: number): void {
    activeBeat.value = null;
    beatChapterId.value = chapterId;
    beatSceneId.value = sceneId;
    beatDialogOpen.value = true;
}

function openEditBeat(
    chapterId: number,
    sceneId: number,
    beat: StructureBeat,
): void {
    activeBeat.value = beat;
    beatChapterId.value = chapterId;
    beatSceneId.value = sceneId;
    beatDialogOpen.value = true;
}

// --- Deletes (confirmed; the server re-checks every guard) ---

async function deleteChapter(chapter: StructureChapter): Promise<void> {
    const confirmed = await confirm({
        title: 'Delete this chapter?',
        description:
            'This permanently removes the chapter and all of its scenes and beats. This cannot be undone.',
        confirmLabel: 'Delete chapter',
    });

    if (!confirmed) {
        return;
    }

    router.delete(
        StructureController.destroyChapter.url({
            story: props.story.slug,
            chapter: chapter.id,
        }),
        { preserveScroll: true },
    );
}

async function deleteScene(
    chapterId: number,
    scene: StructureScene,
): Promise<void> {
    const confirmed = await confirm({
        title: 'Delete this scene?',
        description:
            'This permanently removes the scene and its beats. This cannot be undone.',
        confirmLabel: 'Delete scene',
    });

    if (!confirmed) {
        return;
    }

    router.delete(
        StructureController.destroyScene.url({
            story: props.story.slug,
            chapter: chapterId,
            scene: scene.id,
        }),
        { preserveScroll: true },
    );
}

async function deleteBeat(
    chapterId: number,
    sceneId: number,
    beat: StructureBeat,
): Promise<void> {
    const confirmed = await confirm({
        title: 'Delete this beat?',
        description: 'This permanently removes the beat. This cannot be undone.',
        confirmLabel: 'Delete beat',
    });

    if (!confirmed) {
        return;
    }

    router.delete(
        StructureController.destroyBeat.url({
            story: props.story.slug,
            chapter: chapterId,
            scene: sceneId,
            beat: beat.id,
        }),
        { preserveScroll: true },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Workspace', href: dashboard() },
            { title: 'Structure', href: '' },
        ],
    },
});
</script>

<template>
    <Head :title="`${props.story.title} · Structure`" />

    <!-- Heading + single primary action -->
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="space-y-1">
            <h2 class="text-base font-semibold text-foreground">Structure</h2>
            <p class="max-w-prose text-sm text-muted-foreground">
                Author the chapter → scene → beat the loop plays through. A scene
                sets the POV and present cast; a beat carries the goal the
                narrator steers toward.
            </p>
        </div>
        <div v-if="props.chapters.length > 0" class="shrink-0">
            <Button class="h-11" data-test="new-chapter" @click="openCreateChapter">
                <Plus class="size-4" />
                New chapter
            </Button>
        </div>
    </header>

    <!-- No-cast notice: scenes need a viewpoint character -->
    <div
        v-if="!hasCharacters"
        class="flex flex-col gap-3 rounded-lg border border-dashed border-border bg-muted/40 p-4 text-sm sm:flex-row sm:items-center sm:justify-between"
        data-test="structure-no-characters"
    >
        <p class="text-muted-foreground">
            Scenes need at least one character to anchor their point of view. Add
            the cast first, then build the structure.
        </p>
        <Button as-child variant="outline" class="h-11 shrink-0">
            <Link :href="charactersIndex(props.story.slug)">
                <Users class="size-4" />
                Go to Characters
            </Link>
        </Button>
    </div>

    <!-- Empty state: no chapters yet -->
    <EmptyState
        v-if="props.chapters.length === 0"
        :icon="ListTree"
        title="No structure yet"
        description="Author the chapter, scene, and beat the loop plays through — by hand, with no model call. Start with a chapter."
        data-test="structure-empty"
    >
        <template #action>
            <Button class="h-11" data-test="new-chapter" @click="openCreateChapter">
                <Plus class="size-4" />
                New chapter
            </Button>
        </template>
    </EmptyState>

    <!-- Chapter → scene → beat tree -->
    <div v-else class="space-y-4" data-test="structure-tree">
        <Card
            v-for="chapter in props.chapters"
            :key="chapter.id"
            :data-test="`chapter-${chapter.id}`"
        >
            <!-- Chapter header -->
            <CardHeader
                class="flex flex-row items-start justify-between gap-3 space-y-0"
            >
                <div class="space-y-1">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <BookMarked class="size-4 text-muted-foreground" />
                        <span class="line-clamp-1">
                            Chapter {{ chapter.number }} · {{ chapter.title }}
                        </span>
                    </CardTitle>
                    <Badge variant="secondary" class="gap-1">
                        <Eye class="size-3" />
                        {{ povLabel(chapter.povDefault) }}
                    </Badge>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9"
                        :data-test="`edit-chapter-${chapter.id}`"
                        @click="openEditChapter(chapter)"
                    >
                        <Pencil class="size-4" />
                        <span class="sr-only">Edit chapter</span>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9 text-destructive hover:text-destructive"
                        :disabled="!chapter.canDelete"
                        :title="
                            chapter.canDelete
                                ? undefined
                                : 'This chapter anchors character cards — move or delete those characters first.'
                        "
                        :data-test="`delete-chapter-${chapter.id}`"
                        @click="deleteChapter(chapter)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Delete chapter</span>
                    </Button>
                </div>
            </CardHeader>

            <!-- Scenes -->
            <CardContent class="space-y-3">
                <div
                    v-for="scene in chapter.scenes"
                    :key="scene.id"
                    class="rounded-lg border border-border p-4"
                    :data-test="`scene-${scene.id}`"
                >
                    <!-- Scene header: POV contract + controls -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-sm font-medium">
                                <Clapperboard class="size-4 text-muted-foreground" />
                                Scene {{ scene.number }}
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge variant="secondary" class="gap-1">
                                    <Eye class="size-3" />
                                    {{ povLabel(scene.povMode) }}
                                </Badge>
                                <Badge variant="outline">
                                    POV: {{ characterName(scene.povAnchor) }}
                                </Badge>
                                <Badge v-if="scene.tone" variant="outline">
                                    {{ scene.tone }}
                                </Badge>
                            </div>
                            <p
                                v-if="scene.presentCharacters.length > 0"
                                class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Users class="size-3.5" />
                                <span
                                    v-for="slug in scene.presentCharacters"
                                    :key="slug"
                                >
                                    {{ characterName(slug) }}
                                </span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-9"
                                :data-test="`edit-scene-${scene.id}`"
                                @click="openEditScene(chapter.id, scene)"
                            >
                                <Pencil class="size-4" />
                                <span class="sr-only">Edit scene</span>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-9 text-destructive hover:text-destructive"
                                :data-test="`delete-scene-${scene.id}`"
                                @click="deleteScene(chapter.id, scene)"
                            >
                                <Trash2 class="size-4" />
                                <span class="sr-only">Delete scene</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Beats -->
                    <ul class="mt-3 space-y-2 border-t border-border pt-3">
                        <li
                            v-for="beat in scene.beats"
                            :key="beat.id"
                            class="flex items-center justify-between gap-3"
                            :data-test="`beat-${beat.id}`"
                        >
                            <span class="flex items-center gap-2 text-sm">
                                <Target class="size-4 shrink-0 text-muted-foreground" />
                                <span>{{ beat.goal }}</span>
                            </span>
                            <div class="flex shrink-0 items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-9"
                                    :data-test="`edit-beat-${beat.id}`"
                                    @click="openEditBeat(chapter.id, scene.id, beat)"
                                >
                                    <Pencil class="size-4" />
                                    <span class="sr-only">Edit beat</span>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-9 text-destructive hover:text-destructive"
                                    :data-test="`delete-beat-${beat.id}`"
                                    @click="deleteBeat(chapter.id, scene.id, beat)"
                                >
                                    <Trash2 class="size-4" />
                                    <span class="sr-only">Delete beat</span>
                                </Button>
                            </div>
                        </li>
                        <li>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-9 text-muted-foreground"
                                :data-test="`new-beat-${scene.id}`"
                                @click="openCreateBeat(chapter.id, scene.id)"
                            >
                                <Plus class="size-4" />
                                Add beat
                            </Button>
                        </li>
                    </ul>
                </div>

                <!-- Add scene (needs a cast to anchor the POV) -->
                <Button
                    variant="outline"
                    class="h-11 w-full"
                    :disabled="!hasCharacters"
                    :data-test="`new-scene-${chapter.id}`"
                    @click="openCreateScene(chapter.id)"
                >
                    <Plus class="size-4" />
                    Add scene
                </Button>
            </CardContent>
        </Card>
    </div>

    <!-- Create / edit dialogs (one instance per level serves both modes) -->
    <ChapterDialog
        v-model:open="chapterDialogOpen"
        :story-slug="props.story.slug"
        :pov-options="props.povOptions"
        :default-pov="props.defaultPov"
        :entry="activeChapter"
    />
    <SceneDialog
        v-if="sceneChapterId !== null"
        v-model:open="sceneDialogOpen"
        :story-slug="props.story.slug"
        :chapter-id="sceneChapterId"
        :characters="props.characters"
        :pov-options="props.povOptions"
        :default-pov="props.defaultPov"
        :entry="activeScene"
    />
    <BeatDialog
        v-if="beatChapterId !== null && beatSceneId !== null"
        v-model:open="beatDialogOpen"
        :story-slug="props.story.slug"
        :chapter-id="beatChapterId"
        :scene-id="beatSceneId"
        :entry="activeBeat"
    />
</template>
