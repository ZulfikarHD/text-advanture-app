<script setup lang="ts">
/**
 * Dashboard - the play-first home (E0.1 / S-0.1.1).
 *
 * The front door: each book is something to *play*, not just edit. Every card
 * leads with a one-tap Play/Continue that drops the player straight into the
 * Writing/Play page (resuming the latest playthrough or silently starting one),
 * with Open (the authoring workspace) and edit/delete kept secondary. When empty,
 * a teaching EmptyState offers the single "New story" CTA. Delete uses useConfirm.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Pencil, Play, Plus, Settings2, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import SessionController from '@/actions/App/Http/Controllers/Stories/SessionController';
import StoryController from '@/actions/App/Http/Controllers/Stories/StoryController';
import EmptyState from '@/components/EmptyState.vue';
import CreateStoryDialog from '@/components/stories/CreateStoryDialog.vue';
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
import { show as storyShow } from '@/routes/stories';

type ResumePoint = {
    chapterNumber: number | null;
    chapterTitle: string | null;
    lastPlayedForHumans: string | null;
};

type StorySummary = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    updatedAtForHumans: string | null;
    resume: ResumePoint | null;
};

const props = defineProps<{
    stories: StorySummary[];
}>();

const showCreate = ref(false);
const { confirm } = useConfirm();

async function deleteStory(story: StorySummary): Promise<void> {
    const confirmed = await confirm({
        title: `Delete "${story.title}"?`,
        description:
            'This will permanently remove the story and all its authoring data (characters, chapters, lorebook). This cannot be undone.',
        confirmLabel: 'Delete story',
    });

    if (!confirmed) {
        return;
    }

    router.delete(StoryController.destroy.url({ story: story.slug }), {
        preserveScroll: true,
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Workspace',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Workspace" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Page heading -->
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-1">
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Workspace
                </h1>
                <p class="text-sm text-muted-foreground">
                    Pick up where you left off, or start something new.
                </p>
            </div>
            <Button
                v-if="props.stories.length > 0"
                class="h-11 shrink-0"
                data-test="new-story-button"
                @click="showCreate = true"
            >
                <Plus class="size-4" />
                New story
            </Button>
        </header>

        <!-- Empty state: the author has no stories yet -->
        <EmptyState
            v-if="props.stories.length === 0"
            :icon="BookOpen"
            title="No stories yet"
            description="Start by creating your first story. It becomes the container for your characters, chapters, lorebook, and everything else."
        >
            <template #action>
                <Button
                    class="h-11"
                    data-test="new-story-button"
                    @click="showCreate = true"
                >
                    <Plus class="size-4" />
                    New story
                </Button>
            </template>
        </EmptyState>

        <!-- Story grid -->
        <div
            v-else
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            data-test="story-grid"
        >
            <Card
                v-for="story in props.stories"
                :key="story.id"
                class="flex flex-col"
                :data-test="`story-card-${story.slug}`"
            >
                <CardHeader>
                    <CardTitle class="line-clamp-1">
                        <Link
                            :href="storyShow(story.slug)"
                            class="transition-colors hover:text-primary focus-visible:text-primary focus-visible:outline-none"
                            :data-test="`open-story-${story.slug}`"
                        >
                            {{ story.title }}
                        </Link>
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex-1 space-y-3">
                    <p
                        v-if="story.description"
                        class="line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ story.description }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground/60 italic">
                        No description
                    </p>

                    <!-- Resume hint: where the latest playthrough left off -->
                    <p
                        v-if="story.resume"
                        class="flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground"
                        :data-test="`resume-${story.slug}`"
                    >
                        <span class="font-medium text-foreground">
                            Chapter {{ story.resume.chapterNumber }}
                        </span>
                        <span v-if="story.resume.chapterTitle" class="truncate">
                            · {{ story.resume.chapterTitle }}
                        </span>
                        <span v-if="story.resume.lastPlayedForHumans">
                            · {{ story.resume.lastPlayedForHumans }}
                        </span>
                    </p>
                    <p
                        v-else-if="story.updatedAtForHumans"
                        class="text-xs text-muted-foreground"
                    >
                        Updated {{ story.updatedAtForHumans }}
                    </p>
                </CardContent>
                <CardFooter
                    class="flex flex-col gap-2 border-t border-border pt-4"
                >
                    <!-- Primary: drop straight into play -->
                    <Button
                        as-child
                        class="h-11 w-full"
                        :data-test="`play-story-${story.slug}`"
                    >
                        <Link :href="SessionController.enter(story.slug)">
                            <Play class="size-4" />
                            {{ story.resume ? 'Continue' : 'Play' }}
                        </Link>
                    </Button>

                    <!-- Secondary: open the authoring workspace + manage -->
                    <div class="flex w-full items-center gap-1">
                        <Button
                            as-child
                            variant="outline"
                            class="h-10 flex-1"
                            :data-test="`workspace-story-${story.slug}`"
                        >
                            <Link :href="storyShow(story.slug)">
                                <Settings2 class="size-4" />
                                Open
                            </Link>
                        </Button>
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            class="size-10"
                            :data-test="`edit-story-${story.slug}`"
                        >
                            <Link
                                :href="
                                    StoryController.edit.url({
                                        story: story.slug,
                                    })
                                "
                            >
                                <Pencil class="size-4" />
                                <span class="sr-only"
                                    >Edit {{ story.title }}</span
                                >
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-10 text-destructive hover:text-destructive"
                            :data-test="`delete-story-${story.slug}`"
                            @click="deleteStory(story)"
                        >
                            <Trash2 class="size-4" />
                            <span class="sr-only">Delete {{ story.title }}</span>
                        </Button>
                    </div>
                </CardFooter>
            </Card>
        </div>
    </div>

    <!-- Create story dialog -->
    <CreateStoryDialog v-model:open="showCreate" />
</template>
