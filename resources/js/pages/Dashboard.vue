<script setup lang="ts">
/**
 * Dashboard - the workspace story list (S-1.1.2).
 *
 * Lists the author's stories with edit/delete actions. When empty, renders a
 * teaching EmptyState with a single primary "New story" CTA that opens the
 * CreateStoryDialog. Story delete uses useConfirm (never native alerts).
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import CreateStoryDialog from '@/components/stories/CreateStoryDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { dashboard } from '@/routes';
import StoryController from '@/actions/App/Http/Controllers/Stories/StoryController';
import { show as storyShow } from '@/routes/stories';

type StorySummary = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    updatedAtForHumans: string | null;
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
                    Create and manage the interactive stories you author.
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
                    <CardDescription class="font-mono text-xs">
                        {{ story.slug }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex-1">
                    <p
                        v-if="story.description"
                        class="line-clamp-3 text-sm text-muted-foreground"
                    >
                        {{ story.description }}
                    </p>
                    <p
                        v-else
                        class="text-sm text-muted-foreground/60 italic"
                    >
                        No description
                    </p>
                </CardContent>
                <CardFooter
                    class="flex items-center justify-between border-t border-border pt-4"
                >
                    <p
                        v-if="story.updatedAtForHumans"
                        class="text-xs text-muted-foreground"
                    >
                        {{ story.updatedAtForHumans }}
                    </p>
                    <div class="flex items-center gap-1">
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            class="size-9"
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
                            class="size-9 text-destructive hover:text-destructive"
                            :data-test="`delete-story-${story.slug}`"
                            @click="deleteStory(story)"
                        >
                            <Trash2 class="size-4" />
                            <span class="sr-only"
                                >Delete {{ story.title }}</span
                            >
                        </Button>
                    </div>
                </CardFooter>
            </Card>
        </div>
    </div>

    <!-- Create story dialog -->
    <CreateStoryDialog v-model:open="showCreate" />
</template>
