<script setup lang="ts">
/**
 * Stories/Saves - the story's save-realm surface (S-2.1.1).
 *
 * Starts a playthrough by forking the play-ready story into the save realm and
 * lists the saves forked from it. Forking never mutates the authoring template
 * (ADR 0012): each save references the immutable structure and evolves on its
 * own. The "Start session" action is gated on play-readiness (re-checked
 * server-side); a not-yet-ready story routes the author back to the Overview to
 * finish the requirements. Each save opens its Play surface (the reachable next
 * step). Multi-save management (rename/load/reset/delete) and resume land in
 * later stories. Rendered inside the per-story workspace layout (tab nav).
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, CircleAlert, Play, Save } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useFormat } from '@/composables/useFormat';
import { dashboard } from '@/routes';
import { show as storyShow } from '@/routes/stories';
import { play as playRoute, store as startSessionRoute } from '@/routes/stories/saves';

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

const unmetRequirements = computed(() =>
    props.readiness.requirements.filter((req) => !req.met),
);

// No payload: the fork is derived entirely from the (server-authorized) story.
// useForm tracks `processing` so the button can show its in-flight state.
const startForm = useForm({});

function startSession(): void {
    startForm.post(startSessionRoute.url({ story: props.story.slug }), {
        preserveScroll: true,
    });
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
            <Button
                class="h-11"
                data-test="start-session"
                :disabled="startForm.processing"
                @click="startSession"
            >
                <Play class="size-4" />
                {{ startForm.processing ? 'Starting…' : 'Start session' }}
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
            <Button
                class="h-11"
                data-test="start-session"
                :disabled="startForm.processing"
                @click="startSession"
            >
                <Play class="size-4" />
                {{ startForm.processing ? 'Starting…' : 'Start session' }}
            </Button>
        </template>
    </EmptyState>

    <!-- Save list (shown whenever saves exist, even if the story drifted out of readiness) -->
    <div
        v-if="props.saves.length > 0"
        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        data-test="saves-grid"
    >
        <Link
            v-for="save in props.saves"
            :key="save.id"
            :href="playRoute({ story: props.story.slug, playSession: save.id })"
            class="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :data-test="`save-${save.id}`"
        >
            <Card class="h-full transition-colors hover:border-primary/40">
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
                    </div>
                    <div
                        class="flex items-center justify-between gap-2 border-t border-border pt-4"
                    >
                        <span class="text-xs text-muted-foreground">
                            {{
                                save.lastPlayedAt
                                    ? formatDateTime(save.lastPlayedAt)
                                    : 'Not played yet'
                            }}
                        </span>
                        <span
                            class="flex items-center gap-1 text-sm font-medium text-muted-foreground transition-colors group-hover:text-foreground"
                        >
                            Open
                            <ArrowRight
                                class="size-4 transition-transform group-hover:translate-x-0.5"
                            />
                        </span>
                    </div>
                </CardContent>
            </Card>
        </Link>
    </div>
</template>
