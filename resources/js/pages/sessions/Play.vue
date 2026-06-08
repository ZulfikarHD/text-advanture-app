<script setup lang="ts">
/**
 * Sessions/Play - the reachable next step after forking a save (S-2.1.1).
 *
 * A placeholder this phase: it orients the player at the save just forked - its
 * loop state and starting position (chapter / scene / beat goal) - so starting a
 * session is never a dead end. The prose reader, scrollback, and advance/pause
 * controls arrive in S-5.4.1; until then this surface teaches what comes next
 * and routes back to the story's saves. Rendered under the app shell (no
 * authoring tab bar) since play is a player surface, not an authoring one.
 */
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    Clapperboard,
    Construction,
    Footprints,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { show as storyShow } from '@/routes/stories';
import { index as savesIndex } from '@/routes/stories/saves';
import type { BreadcrumbItem } from '@/types';

type StoryRef = {
    id: number;
    slug: string;
    title: string;
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
    save: SaveItem;
}>();

// A resume anchor is written once a narrator turn pauses mid-beat (S-5.3.1);
// until then a save opens at the top of its beat. The copy reflects which.
const isResuming = computed(() => props.save.resumeAnchor !== null);

// Breadcrumbs depend on props (story + save), so they're set dynamically here
// and link back through Saves so the player can always navigate out.
setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
    breadcrumbs: [
        { title: 'Workspace', href: dashboard() },
        { title: props.story.title, href: storyShow(props.story.slug) },
        { title: 'Saves', href: savesIndex(props.story.slug) },
        { title: props.save.name, href: '' },
    ],
});
</script>

<template>
    <Head :title="`${props.story.title} · ${props.save.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
        <!-- Session identity -->
        <header class="space-y-2">
            <div class="flex items-center gap-2">
                <Badge variant="secondary" data-test="save-state">
                    {{ props.save.stateLabel }}
                </Badge>
            </div>
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                {{ props.save.name }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Your playthrough of
                <span class="text-foreground">{{ props.story.title }}</span> is
                ready, forked from the authoring template.
            </p>
            <p class="text-sm text-muted-foreground" data-test="save-resume-line">
                {{
                    isResuming
                        ? 'Resuming from where you left off.'
                        : 'Starting fresh at the beginning of the beat.'
                }}
            </p>
        </header>

        <!-- Starting position (where the narrator will begin) -->
        <Card data-test="save-position">
            <CardContent class="space-y-4">
                <h2 class="text-sm font-medium text-muted-foreground">
                    Starting position
                </h2>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                        >
                            <BookOpen class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-foreground">
                                Chapter {{ props.save.position.chapterNumber }}
                            </p>
                            <p
                                v-if="props.save.position.chapterTitle"
                                class="text-xs text-muted-foreground"
                            >
                                {{ props.save.position.chapterTitle }}
                            </p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                        >
                            <Clapperboard class="size-4" />
                        </span>
                        <p class="text-sm font-medium text-foreground">
                            Scene {{ props.save.position.sceneNumber }}
                        </p>
                    </li>
                    <li
                        v-if="props.save.position.beatGoal"
                        class="flex items-start gap-3"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                        >
                            <Footprints class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-foreground">
                                Beat goal
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ props.save.position.beatGoal }}
                            </p>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <!-- Teaching state: the reader/loop is not built yet -->
        <section
            class="flex flex-col items-center gap-5 rounded-xl border border-dashed border-border bg-card/40 p-8 text-center"
            data-test="play-coming-soon"
        >
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
            >
                <Construction class="size-7" />
            </div>
            <div class="space-y-2">
                <h2 class="text-lg font-medium text-foreground">
                    The play surface is coming soon
                </h2>
                <p class="mx-auto max-w-md text-sm text-muted-foreground">
                    Narrated prose, a readable scrollback, and advance / pause
                    controls arrive in S-5.4.1. Your save is stored and waiting
                    at the position above.
                </p>
            </div>
            <Button
                as-child
                class="h-11"
                variant="outline"
                data-test="play-back-to-saves"
            >
                <Link :href="savesIndex(props.story.slug)">
                    <ArrowLeft class="size-4" />
                    Back to saves
                </Link>
            </Button>
        </section>
    </div>
</template>
