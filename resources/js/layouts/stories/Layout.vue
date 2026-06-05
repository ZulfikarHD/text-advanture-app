<script setup lang="ts">
/**
 * Story workspace layout - the per-story sub-navigation shell (E1.2 / S-1.2.x).
 *
 * Wraps every per-story surface (Overview / Details / Settings) so each one is
 * scoped to a single story and reachable by tab navigation rather than a typed
 * URL. Reads the shared `story` page prop that all three story pages expose.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { edit as storyEdit, show as storyShow } from '@/routes/stories';
import { edit as storySettingsEdit } from '@/routes/stories/settings';
import type { NavItem } from '@/types';

type StoryRef = { id: number; slug: string; title: string };

const page = usePage<{ story: StoryRef }>();
const story = computed(() => page.props.story);

const { isCurrentUrl } = useCurrentUrl();

const tabs = computed<NavItem[]>(() => [
    { title: 'Overview', href: storyShow(story.value.slug) },
    { title: 'Details', href: storyEdit(story.value.slug) },
    { title: 'Settings', href: storySettingsEdit(story.value.slug) },
]);
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Workspace header: back to the dashboard + story identity -->
        <div class="space-y-3">
            <Button
                as-child
                variant="ghost"
                size="sm"
                class="-ml-2 h-9 w-fit text-muted-foreground"
            >
                <Link :href="dashboard()">
                    <ArrowLeft class="size-4" />
                    Workspace
                </Link>
            </Button>
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                    {{ story.title }}
                </h1>
                <p class="font-mono text-xs text-muted-foreground">
                    {{ story.slug }}
                </p>
            </div>
        </div>

        <!-- Per-story tab navigation -->
        <nav
            class="flex flex-wrap gap-1 border-b border-border"
            aria-label="Story workspace"
        >
            <Button
                v-for="tab in tabs"
                :key="tab.title"
                as-child
                variant="ghost"
                class="h-11 rounded-b-none border-b-2 px-4"
                :class="
                    isCurrentUrl(tab.href)
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground'
                "
                :data-test="`story-tab-${tab.title.toLowerCase()}`"
            >
                <Link :href="tab.href">{{ tab.title }}</Link>
            </Button>
        </nav>

        <!-- Active surface -->
        <slot />
    </div>
</template>
