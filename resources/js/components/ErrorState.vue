<script setup lang="ts">
/**
 * ErrorState - the standard failure surface for an async operation.
 *
 * One of the four required async states (loading / empty / error / success).
 * Explains what went wrong and, where possible, how to recover via an optional
 * retry action passed through the `action` slot.
 */
import { AlertCircle } from '@lucide/vue';

withDefaults(
    defineProps<{
        /** Short headline describing the failure. */
        title?: string;
        /** Human-readable explanation of what went wrong. */
        message?: string;
    }>(),
    {
        title: 'Something went wrong',
    },
);
</script>

<template>
    <section
        class="flex flex-1 flex-col items-center justify-center rounded-xl border border-destructive/30 bg-destructive/5 p-8 text-center"
    >
        <div
            class="flex size-14 items-center justify-center rounded-2xl bg-destructive/10 text-destructive"
        >
            <AlertCircle class="size-7" />
        </div>

        <h2 class="mt-5 text-lg font-medium text-foreground">{{ title }}</h2>
        <p v-if="message" class="mt-2 max-w-md text-sm text-muted-foreground">
            {{ message }}
        </p>

        <div v-if="$slots.action" class="mt-6">
            <slot name="action" />
        </div>
    </section>
</template>
