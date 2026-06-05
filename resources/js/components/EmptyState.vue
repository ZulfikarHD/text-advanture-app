<script setup lang="ts">
/**
 * EmptyState - the standard "nothing here yet" surface.
 *
 * One of the four required async states (loading / empty / error / success).
 * An empty state teaches the next step rather than showing a blank screen:
 * an icon, a title, a short description, and a single primary action passed
 * via the `action` slot (Hick's Law - one primary action per view).
 */
import type { Component } from 'vue';

defineProps<{
    /** Lucide icon component shown in the badge. */
    icon?: Component;
    /** Short headline, e.g. "No stories yet". */
    title: string;
    /** One or two sentences explaining the state and the next step. */
    description?: string;
}>();
</script>

<template>
    <section
        class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card/40 p-8 text-center"
    >
        <div
            v-if="icon"
            class="flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
        >
            <component :is="icon" class="size-7" />
        </div>

        <h2 class="mt-5 text-lg font-medium text-foreground">{{ title }}</h2>
        <p
            v-if="description"
            class="mt-2 max-w-md text-sm text-muted-foreground"
        >
            {{ description }}
        </p>

        <div v-if="$slots.action" class="mt-6">
            <slot name="action" />
        </div>
    </section>
</template>
