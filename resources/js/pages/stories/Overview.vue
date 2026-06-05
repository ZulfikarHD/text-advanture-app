<script setup lang="ts">
/**
 * Stories/Overview - the story's authoring inventory + play-readiness (S-1.2.2).
 *
 * The default surface of a story's workspace. Every count and the readiness
 * gate are derived server-side on read (never stored), so this page just
 * presents them: a readiness panel that enumerates what is still missing, and a
 * grid of authoring counts. Rendered inside the per-story workspace layout.
 */
import { Head } from '@inertiajs/vue3';
import {
    BookMarked,
    BookOpen,
    CircleAlert,
    CircleCheck,
    Film,
    Footprints,
    Lock,
    Save,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';

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
}>();

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
                    <div class="space-y-0.5">
                        <p class="text-sm font-medium text-foreground">
                            {{ req.label }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ req.detail }}
                        </p>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>

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
