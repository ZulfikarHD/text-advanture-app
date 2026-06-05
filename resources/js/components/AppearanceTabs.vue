<script setup lang="ts">
/**
 * AppearanceTabs - segmented control for switching the color theme.
 *
 * Offers Light / Dark / System, backed by `useAppearance` (persists to
 * localStorage + cookie so the choice survives reloads and SSR first paint).
 * Token-only styling guarantees light/dark parity. Reused on the Appearance
 * settings page and, in `block` mode, inside the shell user menu.
 */
import { Monitor, Moon, Sun } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { cn } from '@/lib/utils';

const props = defineProps<{
    /** Extra classes for the container. */
    class?: HTMLAttributes['class'];
    /** Stretch the control to full width with equal-width options (menu use). */
    block?: boolean;
}>();

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div
        role="radiogroup"
        aria-label="Color theme"
        :class="
            cn(
                'gap-1 rounded-lg bg-muted p-1',
                props.block ? 'flex w-full' : 'inline-flex',
                props.class,
            )
        "
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            role="radio"
            :aria-checked="appearance === value"
            :aria-label="label"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                props.block ? 'flex-1 justify-center' : '',
                appearance === value
                    ? 'bg-background text-foreground shadow-xs'
                    : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
