<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen, Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

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
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                Workspace
            </h1>
            <p class="text-sm text-muted-foreground">
                Create and manage the interactive stories you author.
            </p>
        </header>

        <!-- Empty state: the author has no stories yet -->
        <section
            class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card/40 p-8 text-center"
        >
            <div
                class="flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
            >
                <BookOpen class="size-7" />
            </div>

            <h2 class="mt-5 text-lg font-medium text-foreground">
                No stories yet
            </h2>
            <p class="mt-2 max-w-md text-sm text-muted-foreground">
                Your authored stories will live here. Story authoring arrives in
                the next phase — once it lands, you'll start your first story
                right from this screen.
            </p>

            <!--
                Single primary action (Hick's Law). Disabled because story
                creation ships in Phase 2; rendered as a clearly-labelled
                "coming soon" affordance rather than a dead link.
            -->
            <Button
                class="mt-6 h-11"
                disabled
                aria-disabled="true"
                title="Story authoring arrives in the next phase"
            >
                <Plus class="size-4" />
                New story
            </Button>
            <p class="mt-2 text-xs text-muted-foreground">Coming soon</p>
        </section>
    </div>
</template>
