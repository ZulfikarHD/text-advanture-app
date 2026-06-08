<script setup lang="ts">
/**
 * ModelCombobox - a searchable picker over the provider's reachable models.
 *
 * Authors choose a model by searching the live catalog (which spans both
 * OpenRouter-native and Claude `anthropic/*` models) instead of hand-typing a
 * slug - search beats scanning hundreds of options (Hick's Law). A custom slug
 * is still accepted when the catalog can't be reached or lists a brand-new
 * model, so the field never traps the author (Postel's Law).
 */
import { Check, ChevronsUpDown, Search } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type ModelOption = {
    id: string;
    name: string;
    contextLength: number | null;
};

const props = withDefaults(
    defineProps<{
        modelValue: string;
        models: ModelOption[];
        loading?: boolean;
        disabled?: boolean;
        id?: string;
        placeholder?: string;
        invalid?: boolean;
    }>(),
    {
        loading: false,
        disabled: false,
        id: undefined,
        placeholder: 'Search a model…',
        invalid: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

// Cap the rendered rows so the dropdown stays snappy even with a 300+ model
// catalog (Doherty Threshold) - typing narrows it well before the cap bites.
const MAX_RESULTS = 50;

const open = ref(false);
const search = ref('');

const filtered = computed<ModelOption[]>(() => {
    const term = search.value.trim().toLowerCase();

    const matches =
        term === ''
            ? props.models
            : props.models.filter(
                  (model) =>
                      model.id.toLowerCase().includes(term) ||
                      model.name.toLowerCase().includes(term),
              );

    return matches.slice(0, MAX_RESULTS);
});

const totalMatches = computed<number>(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return props.models.length;
    }

    return props.models.filter(
        (model) =>
            model.id.toLowerCase().includes(term) ||
            model.name.toLowerCase().includes(term),
    ).length;
});

// Offer the typed text as a selectable slug when it matches nothing in the
// catalog, so the author is never blocked from entering a valid model.
const customSlug = computed<string | null>(() => {
    const term = search.value.trim();

    if (term === '' || props.models.some((model) => model.id === term)) {
        return null;
    }

    return term;
});

watch(open, (isOpen) => {
    if (!isOpen) {
        search.value = '';
    }
});

function onSelect(value: string): void {
    emit('update:modelValue', value);
    open.value = false;
}

function contextLabel(length: number | null): string | null {
    return length === null ? null : `${Math.round(length / 1000)}K context`;
}
</script>

<template>
    <ComboboxRoot
        :model-value="modelValue"
        :open="open"
        :disabled="disabled"
        :ignore-filter="true"
        :reset-search-term-on-blur="false"
        :reset-search-term-on-select="false"
        class="relative"
        @update:model-value="(value) => onSelect(value as string)"
        @update:open="(value) => (open = value)"
    >
        <ComboboxAnchor as-child>
            <ComboboxTrigger
                :id="id"
                type="button"
                :aria-invalid="invalid || undefined"
                :class="
                    cn(
                        'flex h-11 w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20',
                    )
                "
            >
                <span
                    v-if="modelValue"
                    class="truncate font-mono text-foreground"
                    data-test="model-combobox-value"
                >
                    {{ modelValue }}
                </span>
                <span v-else class="truncate text-muted-foreground">
                    {{ placeholder }}
                </span>
                <ChevronsUpDown class="size-4 shrink-0 opacity-50" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                class="z-50 max-h-(--reka-combobox-content-available-height) w-(--reka-combobox-trigger-width) min-w-[16rem] overflow-hidden rounded-md border border-border bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
            >
                <div
                    class="flex items-center gap-2 border-b border-border px-3"
                >
                    <Search class="size-4 shrink-0 text-muted-foreground" />
                    <ComboboxInput
                        v-model="search"
                        auto-focus
                        class="h-10 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        placeholder="Search models…"
                    />
                </div>

                <ComboboxViewport class="max-h-72 overflow-y-auto p-1">
                    <div
                        v-if="loading"
                        class="flex items-center gap-2 px-2 py-6 text-sm text-muted-foreground"
                    >
                        <Spinner class="size-4" />
                        Loading models…
                    </div>

                    <template v-else>
                        <ComboboxItem
                            v-if="customSlug"
                            :value="customSlug"
                            class="relative flex w-full cursor-default items-center gap-2 rounded-sm px-2 py-2 text-sm outline-hidden select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                        >
                            <span class="text-muted-foreground">Use custom slug</span>
                            <span class="truncate font-mono text-foreground">{{ customSlug }}</span>
                        </ComboboxItem>

                        <ComboboxItem
                            v-for="model in filtered"
                            :key="model.id"
                            :value="model.id"
                            :text-value="`${model.name} ${model.id}`"
                            class="relative flex w-full cursor-default flex-col items-start gap-0.5 rounded-sm py-2 pr-8 pl-2 text-sm outline-hidden select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                        >
                            <span class="truncate font-medium text-foreground">
                                {{ model.name }}
                            </span>
                            <span class="flex items-center gap-2 text-xs text-muted-foreground">
                                <span class="truncate font-mono">{{ model.id }}</span>
                                <span v-if="contextLabel(model.contextLength)">
                                    · {{ contextLabel(model.contextLength) }}
                                </span>
                            </span>
                            <ComboboxItemIndicator
                                class="absolute top-2.5 right-2 text-primary"
                            >
                                <Check class="size-4" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>

                        <ComboboxEmpty
                            v-if="filtered.length === 0 && !customSlug"
                            class="px-2 py-6 text-center text-sm text-muted-foreground"
                        >
                            No models match your search.
                        </ComboboxEmpty>

                        <p
                            v-if="totalMatches > filtered.length"
                            class="px-2 pt-1 pb-1.5 text-center text-xs text-muted-foreground"
                        >
                            Showing {{ filtered.length }} of {{ totalMatches }} —
                            keep typing to narrow.
                        </p>
                    </template>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
