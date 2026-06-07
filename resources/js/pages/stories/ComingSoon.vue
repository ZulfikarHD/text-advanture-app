<script setup lang="ts">
/**
 * Stories/ComingSoon - shared placeholder for unbuilt workspace surfaces
 * (E2.1 / S-2.1.1).
 *
 * The workspace nav spans every authoring surface, but Characters / Structure /
 * Saves land in later phases. Each renders this teaching empty state instead of
 * a dead link, so the author can see the full workspace shape today. One
 * component serves the remaining surfaces; the `surface` prop drives the copy
 * and the `surface.key` selects the icon. Rendered inside the per-story
 * workspace layout, which supplies the story title + tab bar. Tracked as PH-30.
 */
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, Clock, Save, Users } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { show as storyShow } from '@/routes/stories';
import type { BreadcrumbItem } from '@/types';

type Surface = {
    key: 'characters' | 'saves';
    title: string;
    description: string;
    phase: string;
};

type StoryRef = {
    id: number;
    slug: string;
    title: string;
};

const props = defineProps<{
    story: StoryRef;
    surface: Surface;
}>();

// Backend can't ship Vue components, so map the surface key to its icon here.
const surfaceIcons: Record<Surface['key'], LucideIcon> = {
    characters: Users,
    saves: Save,
};

const icon = computed<LucideIcon>(() => surfaceIcons[props.surface.key]);

// Breadcrumb depends on the surface, so it's set dynamically here rather than
// via the static defineOptions form the sibling pages use.
setLayoutProps<{ breadcrumbs: BreadcrumbItem[] }>({
    breadcrumbs: [
        { title: 'Workspace', href: dashboard() },
        { title: props.surface.title, href: '' },
    ],
});
</script>

<template>
    <Head :title="`${props.story.title} · ${props.surface.title}`" />

    <EmptyState
        :icon="icon"
        :title="props.surface.title"
        :description="props.surface.description"
        :data-test="`coming-soon-${props.surface.key}`"
    >
        <template #action>
            <div class="flex flex-col items-center gap-4">
                <Badge variant="secondary" class="gap-1.5">
                    <Clock class="size-3.5" />
                    Coming in {{ props.surface.phase }}
                </Badge>
                <Button
                    as-child
                    variant="outline"
                    class="h-11"
                    data-test="coming-soon-back"
                >
                    <Link :href="storyShow(props.story.slug)">
                        <ArrowLeft class="size-4" />
                        Back to overview
                    </Link>
                </Button>
            </div>
        </template>
    </EmptyState>
</template>
