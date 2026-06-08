<script setup lang="ts">
/**
 * Story workspace layout - the per-story authoring shell (E2.1 / S-2.1.1).
 *
 * Wraps every per-story surface so each is scoped to a single story and
 * reachable by tab navigation rather than a typed URL. The nav spans the full
 * authoring surface set: Overview, Characters, Structure, Lorebook, Reveal
 * ledger, Settings, Saves, and Details — every surface is now live (Saves
 * starts and lists playthroughs as of S-2.1.1). Reads the shared `story` page
 * prop every story page exposes.
 */
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookMarked,
    FileText,
    KeyRound,
    LayoutDashboard,
    ListTree,
    Save,
    Settings,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { edit as storyEdit, show as storyShow } from '@/routes/stories';
import { index as charactersIndex } from '@/routes/stories/characters';
import { index as lorebookIndex } from '@/routes/stories/lorebook';
import { index as revealLedgerIndex } from '@/routes/stories/reveal-ledger';
import { index as savesIndex } from '@/routes/stories/saves';
import { edit as storySettingsEdit } from '@/routes/stories/settings';
import { index as structureIndex } from '@/routes/stories/structure';
import type { NavItem } from '@/types';

type StoryRef = { id: number; slug: string; title: string };

const page = usePage<{ story: StoryRef }>();
const story = computed(() => page.props.story);

const { isCurrentUrl } = useCurrentUrl();

const tabs = computed<NavItem[]>(() => [
    { title: 'Overview', href: storyShow(story.value.slug), icon: LayoutDashboard },
    { title: 'Characters', href: charactersIndex(story.value.slug), icon: Users },
    { title: 'Structure', href: structureIndex(story.value.slug), icon: ListTree },
    { title: 'Lorebook', href: lorebookIndex(story.value.slug), icon: BookMarked },
    { title: 'Reveal ledger', href: revealLedgerIndex(story.value.slug), icon: KeyRound },
    { title: 'Settings', href: storySettingsEdit(story.value.slug), icon: Settings },
    { title: 'Saves', href: savesIndex(story.value.slug), icon: Save },
    { title: 'Details', href: storyEdit(story.value.slug), icon: FileText },
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
                <Link :href="tab.href">
                    <component :is="tab.icon" class="size-4" />
                    {{ tab.title }}
                </Link>
            </Button>
        </nav>

        <!-- Active surface -->
        <slot />
    </div>
</template>
