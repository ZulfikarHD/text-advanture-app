<script setup lang="ts">
/**
 * Sessions/Play - the Writing/Play page, the heart of "where I play" (E0.4).
 *
 * The Novel-Crafter-style workspace every play feature mounts into: a codex rail
 * (position, cast, world), a serif prose scrollback that reads the save's scene
 * log, and a single turn control that adapts to whose turn it is — the narrator
 * advances, the player writes back, the player continues past a beat boundary, or
 * the story ends. The underlying fork stays invisible: the player arrived here by
 * opening a book or a chapter, never by managing saves. Reading is sacred (serif,
 * max-w-prose, relaxed leading); chrome stays at the edges. The full narrator
 * memory, streaming, and NPC turns layer onto this same host in later phases.
 */
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpenText,
    CornerDownLeft,
    Flag,
    GitBranch,
    Sparkles,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref, useTemplateRef, watch } from 'vue';
import SessionController from '@/actions/App/Http/Controllers/Stories/SessionController';
import CodexPanel from '@/components/play/CodexPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
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

type TimelineEvent = {
    id: number;
    type: string;
    content: string;
    speaker: string | null;
    createdAt: string | null;
};

type Codex = {
    characters: { id: number; name: string; slug: string; isPlayer: boolean }[];
    lore: { id: number; title: string | null }[];
};

type Flow = {
    state: string;
    awaitingNarrator: boolean;
    awaitingPlayer: boolean;
    atBeatBoundary: boolean;
    ended: boolean;
};

const props = defineProps<{
    story: StoryRef;
    save: SaveItem;
    timeline: TimelineEvent[];
    codex: Codex;
    flow: Flow;
}>();

const scrollRegion = ref<HTMLElement | null>(null);
const inputRef = useTemplateRef('inputRef');
const busy = ref(false);
const inputForm = useForm<{ content: string }>({ content: '' });

// Mirror the server cap (SubmitPlayerInputRequest: content max:5000) so the
// player sees the limit before a round-trip rejects an over-long contribution.
const inputMaxLength = 5000;
const overInputLimit = computed(
    () => inputForm.content.length > inputMaxLength,
);

const hasProse = computed(() => props.timeline.length > 0);

// The narrator's first turn opens the scene; afterwards it continues it.
const narrateLabel = computed(() =>
    hasProse.value ? 'Continue' : 'Begin the scene',
);

/**
 * Split an event's content into display paragraphs, dropping blank lines.
 */
function paragraphs(content: string): string[] {
    return content
        .split(/\n+/)
        .map((line) => line.trim())
        .filter((line) => line.length > 0);
}

function scrollToLatest(): void {
    nextTick(() => {
        const region = scrollRegion.value;

        if (region) {
            region.scrollTop = region.scrollHeight;
        }
    });
}

/**
 * Move focus to the composer when it becomes the player's turn so writing back
 * is immediate — the loop should feel like a conversation, not a form to find.
 */
function focusInput(): void {
    nextTick(() => {
        inputRef.value?.$el?.focus();
    });
}

/** Run a narrator turn (or continue past a beat) — a body-less save write. */
function advance(url: string): void {
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                busy.value = true;
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

function narrate(): void {
    advance(
        SessionController.narrate.url({
            story: props.story.slug,
            playSession: props.save.id,
        }),
    );
}

function continueBeat(): void {
    advance(
        SessionController.continueBeat.url({
            story: props.story.slug,
            playSession: props.save.id,
        }),
    );
}

function submitInput(): void {
    if (
        inputForm.processing ||
        inputForm.content.trim().length === 0 ||
        overInputLimit.value
    ) {
        return;
    }

    inputForm.post(
        SessionController.input.url({
            story: props.story.slug,
            playSession: props.save.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => inputForm.reset(),
        },
    );
}

onMounted(() => {
    scrollToLatest();

    if (props.flow.awaitingPlayer) {
        focusInput();
    }
});
watch(() => props.timeline.length, scrollToLatest);
// When the narrator hands off, drop the player straight into the composer.
watch(
    () => props.flow.awaitingPlayer,
    (awaiting) => {
        if (awaiting) {
            focusInput();
        }
    },
);

setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
    breadcrumbs: [
        { title: 'Workspace', href: dashboard() },
        { title: props.story.title, href: storyShow(props.story.slug) },
        { title: props.save.name, href: '' },
    ],
});
</script>

<template>
    <Head :title="`${props.story.title} · ${props.save.name}`" />

    <div class="flex h-full flex-1 flex-col">
        <!-- Top chrome: out to the book, identity, codex (mobile), branches -->
        <header
            class="flex items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <div class="flex min-w-0 items-center gap-2">
                <Button
                    as-child
                    variant="ghost"
                    size="icon"
                    class="size-9 shrink-0 text-muted-foreground"
                >
                    <Link
                        :href="storyShow(props.story.slug)"
                        data-test="play-back-to-book"
                    >
                        <ArrowLeft class="size-4" />
                        <span class="sr-only">Back to {{ props.story.title }}</span>
                    </Link>
                </Button>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-foreground">
                        {{ props.save.name }}
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ props.story.title }}
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-1.5">
                <Badge variant="secondary" class="hidden sm:inline-flex" data-test="play-state">
                    {{ props.save.stateLabel }}
                </Badge>

                <!-- Mobile codex (the rail lives in a sheet below lg) -->
                <Sheet>
                    <SheetTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-9 lg:hidden"
                            data-test="play-codex-trigger"
                        >
                            <BookOpenText class="size-4" />
                            <span class="sr-only">Open codex</span>
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" class="w-80 overflow-y-auto">
                        <SheetHeader>
                            <SheetTitle>Codex</SheetTitle>
                            <SheetDescription>
                                Where you are, who's here, and the world around you.
                            </SheetDescription>
                        </SheetHeader>
                        <div class="px-4 pb-6">
                            <CodexPanel
                                :characters="props.codex.characters"
                                :lore="props.codex.lore"
                                :position="props.save.position"
                            />
                        </div>
                    </SheetContent>
                </Sheet>

                <Button
                    as-child
                    variant="ghost"
                    size="icon"
                    class="size-9 text-muted-foreground"
                >
                    <Link
                        :href="savesIndex(props.story.slug)"
                        data-test="play-branches"
                    >
                        <GitBranch class="size-4" />
                        <span class="sr-only">Branches &amp; saves</span>
                    </Link>
                </Button>
            </div>
        </header>

        <!-- Workspace body: codex rail + reading column -->
        <div class="flex min-h-0 flex-1">
            <aside
                class="hidden w-64 shrink-0 overflow-y-auto border-r border-border p-4 xl:w-72 lg:block"
            >
                <CodexPanel
                    :characters="props.codex.characters"
                    :lore="props.codex.lore"
                    :position="props.save.position"
                />
            </aside>

            <div class="flex min-h-0 flex-1 flex-col">
                <!-- Prose scrollback (the reading surface) -->
                <div
                    ref="scrollRegion"
                    class="flex-1 overflow-y-auto"
                    data-test="play-scrollback"
                >
                    <div class="mx-auto w-full max-w-prose px-4 py-8">
                        <!-- Empty: the scene hasn't been opened yet -->
                        <div
                            v-if="!hasProse"
                            class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-border bg-card/40 px-6 py-12 text-center"
                            data-test="play-empty"
                        >
                            <span
                                class="flex size-12 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
                            >
                                <Sparkles class="size-6" />
                            </span>
                            <div class="space-y-1.5">
                                <h2 class="text-lg font-medium text-foreground">
                                    Your scene awaits
                                </h2>
                                <p class="mx-auto max-w-sm text-sm text-muted-foreground">
                                    Press
                                    <span class="font-medium text-foreground">{{ narrateLabel }}</span>
                                    and the narrator will open
                                    <span v-if="props.save.position.chapterTitle">
                                        “{{ props.save.position.chapterTitle }}”</span
                                    ><span v-else> the scene</span>.
                                </p>
                            </div>
                        </div>

                        <!-- Scene log -->
                        <article v-else class="space-y-6" data-test="play-prose">
                            <template
                                v-for="event in props.timeline"
                                :key="event.id"
                            >
                                <!-- Player contribution -->
                                <div
                                    v-if="event.type === 'player_input'"
                                    class="rounded-lg border-l-2 border-primary/50 bg-muted/40 px-4 py-3"
                                    :data-test="`event-${event.id}`"
                                >
                                    <p
                                        class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        {{ event.speaker || 'You' }}
                                    </p>
                                    <p
                                        v-for="(line, index) in paragraphs(event.content)"
                                        :key="index"
                                        class="font-serif text-[0.95rem] leading-[1.75] text-foreground/90 italic"
                                    >
                                        {{ line }}
                                    </p>
                                </div>

                                <!-- Narrator / NPC / system prose -->
                                <div
                                    v-else
                                    class="space-y-4"
                                    :data-test="`event-${event.id}`"
                                >
                                    <p
                                        v-for="(line, index) in paragraphs(event.content)"
                                        :key="index"
                                        class="font-serif text-[1.05rem] leading-[1.8] text-foreground"
                                    >
                                        {{ line }}
                                    </p>
                                </div>
                            </template>
                        </article>
                    </div>
                </div>

                <!-- Turn control: one action, chosen by whose turn it is -->
                <div
                    class="shrink-0 border-t border-border bg-background/85 px-4 py-3 backdrop-blur"
                    data-test="play-controls"
                >
                    <div class="mx-auto w-full max-w-prose">
                        <!-- Narrator's turn -->
                        <Button
                            v-if="props.flow.awaitingNarrator"
                            class="h-11 w-full"
                            :disabled="busy"
                            data-test="play-narrate"
                            @click="narrate"
                        >
                            <Sparkles class="size-4" />
                            {{ busy ? 'The narrator is writing…' : narrateLabel }}
                        </Button>

                        <!-- Player's turn: write back -->
                        <form
                            v-else-if="props.flow.awaitingPlayer"
                            class="space-y-2"
                            @submit.prevent="submitInput"
                        >
                            <Textarea
                                ref="inputRef"
                                v-model="inputForm.content"
                                rows="3"
                                placeholder="Write what you do or say…"
                                class="resize-none"
                                data-test="play-input"
                                @keydown.enter.meta.prevent="submitInput"
                                @keydown.enter.ctrl.prevent="submitInput"
                            />
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs text-muted-foreground">
                                    <kbd class="font-sans">⌘</kbd>/<kbd class="font-sans">Ctrl</kbd>
                                    + Enter to send
                                </p>
                                <div class="flex items-center gap-3">
                                    <span
                                        class="text-xs tabular-nums"
                                        :class="
                                            overInputLimit
                                                ? 'text-destructive'
                                                : 'text-muted-foreground'
                                        "
                                        data-test="play-input-count"
                                    >
                                        {{ inputForm.content.length }}/{{ inputMaxLength }}
                                    </span>
                                    <Button
                                        type="submit"
                                        class="h-11"
                                        :disabled="
                                            inputForm.processing ||
                                            inputForm.content.trim().length === 0 ||
                                            overInputLimit
                                        "
                                        data-test="play-send"
                                    >
                                        <CornerDownLeft class="size-4" />
                                        Send
                                    </Button>
                                </div>
                            </div>
                            <p
                                v-if="inputForm.errors.content"
                                class="text-sm text-destructive"
                            >
                                {{ inputForm.errors.content }}
                            </p>
                        </form>

                        <!-- Beat boundary: continue to the next beat -->
                        <Button
                            v-else-if="props.flow.atBeatBoundary"
                            class="h-11 w-full"
                            variant="secondary"
                            :disabled="busy"
                            data-test="play-continue-beat"
                            @click="continueBeat"
                        >
                            {{ busy ? 'Turning the page…' : 'Continue to the next beat' }}
                            <ArrowRight class="size-4" />
                        </Button>

                        <!-- End of the story -->
                        <div
                            v-else-if="props.flow.ended"
                            class="flex flex-col items-center gap-3 py-1 text-center"
                            data-test="play-ended"
                        >
                            <p
                                class="flex items-center gap-2 text-sm font-medium text-foreground"
                            >
                                <Flag class="size-4" />
                                You've reached the end of the story.
                            </p>
                            <Button as-child variant="outline" class="h-10">
                                <Link :href="storyShow(props.story.slug)">
                                    <ArrowLeft class="size-4" />
                                    Back to the book
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
