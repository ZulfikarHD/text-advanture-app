<script setup lang="ts">
/**
 * Stories/Overview - the story's authoring inventory + play-readiness (S-1.2.2).
 *
 * The default surface of a story's workspace. Every count and the readiness
 * gate are derived server-side on read (never stored), so this page just
 * presents them: a readiness panel that enumerates what is still missing, and a
 * grid of authoring counts. Rendered inside the per-story workspace layout.
 */
import { Head, Link } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    BookMarked,
    BookOpen,
    ChevronRight,
    CircleAlert,
    CircleCheck,
    Film,
    Footprints,
    ListTree,
    Lock,
    Play,
    Save,
    SlidersHorizontal,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import SessionController from '@/actions/App/Http/Controllers/Stories/SessionController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as charactersIndex } from '@/routes/stories/characters';
import { edit as storySettingsEdit } from '@/routes/stories/settings';
import { index as structureIndex } from '@/routes/stories/structure';

type Counts = {
    characters: number;
    chapters: number;
    scenes: number;
    beats: number;
    lorebookEntries: number;
    revealLedgerEntries: number;
    saves: number;
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

type ChapterEntry = {
    id: number;
    number: number;
    title: string;
    playableBeats: number;
};

type StoryData = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
};

const props = defineProps<{
    story: StoryData;
    counts: Counts;
    readiness: Readiness;
    chapters: ChapterEntry[];
}>();

// Play entry is gated on the same readiness the server re-checks; an unfinished
// story shows the spine as a teaching preview rather than a dead end.
const canPlay = computed(() => props.readiness.ready);

type RequirementAction = {
    label: string;
    href: ReturnType<typeof charactersIndex>;
    icon: LucideIcon;
};

/**
 * Map an unmet readiness requirement to the tab that resolves it, so each gap
 * becomes a one-click next step instead of a static "you're missing X".
 */
function requirementAction(key: string): RequirementAction | null {
    switch (key) {
        case 'characters':
            return {
                label: 'Add a character',
                href: charactersIndex(props.story.slug),
                icon: Users,
            };
        case 'structure':
            return {
                label: 'Build the structure',
                href: structureIndex(props.story.slug),
                icon: ListTree,
            };
        case 'model_config':
            return {
                label: 'Configure models',
                href: storySettingsEdit(props.story.slug),
                icon: SlidersHorizontal,
            };
        default:
            return null;
    }
}

const countCards = computed(() => [
    { key: 'characters', label: 'Characters', value: props.counts.characters, icon: Users },
    { key: 'chapters', label: 'Chapters', value: props.counts.chapters, icon: BookOpen },
    { key: 'scenes', label: 'Scenes', value: props.counts.scenes, icon: Film },
    { key: 'beats', label: 'Beats', value: props.counts.beats, icon: Footprints },
    { key: 'lorebook', label: 'Lorebook entries', value: props.counts.lorebookEntries, icon: BookMarked },
    { key: 'reveal', label: 'Reveal ledger', value: props.counts.revealLedgerEntries, icon: Lock },
    { key: 'saves', label: 'Saves', value: props.counts.saves, icon: Save },
]);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Workspace', href: dashboard() },
            { title: 'Overview', href: '' },
        ],
    },
});
</script>

<template>
    <Head :title="`${props.story.title} · Overview`" />

    <!-- Play-readiness gate (derived on read) -->
    <Card data-test="readiness-panel">
        <CardContent class="space-y-4">
            <div class="flex items-start gap-3">
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                    :class="
                        readiness.ready
                            ? 'bg-primary/10 text-primary'
                            : 'bg-muted text-muted-foreground'
                    "
                >
                    <component
                        :is="readiness.ready ? CircleCheck : CircleAlert"
                        class="size-5"
                    />
                </span>
                <div class="min-w-0 space-y-1">
                    <h2 class="text-base font-semibold text-foreground">
                        {{ readiness.ready ? 'Ready to play' : 'Not yet playable' }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            readiness.ready
                                ? 'This story meets every requirement to start a playthrough.'
                                : 'Finish the items below to make this story playable.'
                        }}
                    </p>
                </div>
                <Badge
                    :variant="readiness.ready ? 'default' : 'secondary'"
                    class="ml-auto"
                    data-test="readiness-badge"
                >
                    {{ readiness.ready ? 'Play-ready' : 'Incomplete' }}
                </Badge>
            </div>

            <ul class="space-y-3 border-t border-border pt-4">
                <li
                    v-for="req in readiness.requirements"
                    :key="req.key"
                    class="flex items-start gap-3"
                    :data-test="`requirement-${req.key}`"
                    :data-met="req.met"
                >
                    <component
                        :is="req.met ? CircleCheck : CircleAlert"
                        class="mt-0.5 size-5 shrink-0"
                        :class="req.met ? 'text-primary' : 'text-muted-foreground'"
                    />
                    <div class="min-w-0 flex-1 space-y-0.5">
                        <p class="text-sm font-medium text-foreground">
                            {{ req.label }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ req.detail }}
                        </p>
                    </div>

                    <!-- Unmet → a one-click jump to the tab that fixes it -->
                    <Button
                        v-if="!req.met && requirementAction(req.key)"
                        as-child
                        variant="outline"
                        size="sm"
                        class="ml-auto h-9 shrink-0 self-center"
                        :data-test="`fix-${req.key}`"
                    >
                        <Link :href="requirementAction(req.key)!.href">
                            <component
                                :is="requirementAction(req.key)!.icon"
                                class="size-4"
                            />
                            <span class="hidden sm:inline">
                                {{ requirementAction(req.key)!.label }}
                            </span>
                            <span class="sr-only sm:hidden">
                                {{ requirementAction(req.key)!.label }}
                            </span>
                        </Link>
                    </Button>
                </li>
            </ul>
        </CardContent>
    </Card>

    <!-- Chapter spine — the play entrance (E0.2) -->
    <section class="space-y-3" data-test="chapter-spine">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-medium text-muted-foreground">Chapters</h2>
            <Button
                v-if="canPlay && props.chapters.length > 0"
                as-child
                class="h-10"
                data-test="overview-play"
            >
                <Link :href="SessionController.enter(props.story.slug)">
                    <Play class="size-4" />
                    Play
                </Link>
            </Button>
        </div>

        <!-- No chapters yet: teach the next step -->
        <Card v-if="props.chapters.length === 0">
            <CardContent class="flex items-center gap-3 text-sm text-muted-foreground">
                <BookOpen class="size-4 shrink-0" />
                <span>
                    No chapters yet. Add a chapter in Structure to open the first
                    writing scene.
                </span>
            </CardContent>
        </Card>

        <!-- Chapter list: each row enters the Writing page at that chapter -->
        <Card v-else class="overflow-hidden py-0">
            <CardContent class="p-0">
                <ul class="divide-y divide-border">
                    <li v-for="chapter in props.chapters" :key="chapter.id">
                        <component
                            :is="canPlay ? Link : 'div'"
                            :href="
                                canPlay
                                    ? SessionController.enterChapter({
                                          story: props.story.slug,
                                          chapter: chapter.id,
                                      })
                                    : undefined
                            "
                            class="flex items-center gap-4 px-4 py-3 transition-colors"
                            :class="
                                canPlay
                                    ? 'hover:bg-muted/60 focus-visible:bg-muted/60 focus-visible:outline-none'
                                    : 'opacity-70'
                            "
                            :data-test="`chapter-row-${chapter.id}`"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-sm font-semibold text-muted-foreground tabular-nums"
                            >
                                {{ chapter.number }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-foreground">
                                    {{ chapter.title }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        chapter.playableBeats > 0
                                            ? `${chapter.playableBeats} beat(s)`
                                            : 'No beats yet'
                                    }}
                                </p>
                            </div>
                            <ChevronRight
                                v-if="canPlay"
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                        </component>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <p
            v-if="!canPlay"
            class="text-xs text-muted-foreground"
            data-test="spine-locked-note"
        >
            Finish the play-readiness requirements above to open a chapter.
        </p>
    </section>

    <!-- Authoring inventory (counts derived on read) -->
    <section class="space-y-3">
        <h2 class="text-sm font-medium text-muted-foreground">
            Authoring inventory
        </h2>
        <div
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            data-test="overview-counts"
        >
            <Card
                v-for="card in countCards"
                :key="card.key"
                class="py-4"
                :data-test="`count-${card.key}`"
            >
                <CardContent class="flex items-center gap-4 px-4">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                    >
                        <component :is="card.icon" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p
                            class="text-2xl font-semibold text-foreground tabular-nums"
                        >
                            {{ card.value }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ card.label }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </section>
</template>
