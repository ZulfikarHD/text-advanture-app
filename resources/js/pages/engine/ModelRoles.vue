<script setup lang="ts">
/**
 * ModelRoles - the global role -> model mapping editor (S-5.2.2).
 *
 * Engine calls are routed by role, never a hard-coded slug; this screen edits
 * the app-wide global defaults so an author can tier strong/cheap models
 * without code changes.
 *
 * UX: each role is an independently savable section with its own Save button
 * and dirty/saved state, so the author never scrolls to a far-away footer to
 * commit one change (Fitts's Law + Goal-Gradient). Models are chosen from the
 * provider's live catalog via a searchable picker (Hick's Law) - the catalog
 * spans both OpenRouter-native and Claude `anthropic/*` models.
 */
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import { Check, KeyRound } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import ModelRoleController from '@/actions/App/Http/Controllers/Settings/ModelRoleController';
import ProviderController from '@/actions/App/Http/Controllers/Settings/ProviderController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ModelCombobox from '@/components/ModelCombobox.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { edit } from '@/routes/model-roles';
import { edit as providerSettings } from '@/routes/provider';

type RoleRow = {
    role: string;
    label: string;
    description: string;
    modelSlug: string;
    temperature: number;
    maxTokens: number;
    isActive: boolean;
    configured: boolean;
};

type ModelOption = {
    id: string;
    name: string;
    contextLength: number | null;
};

type Props = {
    roles: RoleRow[];
};

const props = defineProps<Props>();

type RoleFormRow = {
    role: string;
    model_slug: string;
    temperature: number;
    max_tokens: number;
    is_active: boolean;
};

function initialRow(role: RoleRow): RoleFormRow {
    return {
        role: role.role,
        model_slug: role.modelSlug,
        temperature: role.temperature,
        max_tokens: role.maxTokens,
        is_active: role.isActive,
    };
}

// One independent form per role so each section saves on its own.
const roleForms = props.roles.map((role) =>
    useForm<{ roles: RoleFormRow[] }>({ roles: [initialRow(role)] }),
);

// A separate form drives the "Save all" bar. It submits every role in card
// order in one request, so validation errors come back keyed by card index and
// still map to the right section.
const bulkForm = useForm<{ roles: RoleFormRow[] }>({ roles: [] });

const dirtyCount = computed(
    () => roleForms.filter((form) => form.isDirty).length,
);
const hasUnsavedChanges = computed(() => dirtyCount.value > 0);

function saveRole(index: number): void {
    roleForms[index].put(ModelRoleController.update.url(), {
        preserveScroll: true,
        onSuccess: () => {
            // Re-baseline so the section reads "saved", not "unsaved", after.
            roleForms[index].defaults();
            // Drop any stale bulk-save errors now that fresh per-section errors
            // (if any) are the source of truth.
            bulkForm.clearErrors();
        },
    });
}

function saveAllRoles(): void {
    bulkForm
        .transform(() => ({
            roles: roleForms.map((form) => ({ ...form.roles[0] })),
        }))
        .put(ModelRoleController.update.url(), {
            preserveScroll: true,
            onSuccess: () => roleForms.forEach((form) => form.defaults()),
        });
}

function discardAllChanges(): void {
    roleForms.forEach((form) => {
        form.reset();
        form.clearErrors();
    });
    bulkForm.clearErrors();
}

/**
 * Merge a role's per-section and bulk-save errors so the card shows whichever
 * save path last reported a problem.
 */
function errorsForRole(index: number): string[] {
    const own = Object.values(roleForms[index].errors) as string[];
    const bulk = Object.entries(bulkForm.errors as Record<string, string>)
        .filter(([key]) => key.startsWith(`roles.${index}.`))
        .map(([, message]) => message);

    return [...own, ...bulk];
}

function errorForField(
    index: number,
    field: 'model_slug' | 'temperature' | 'max_tokens',
): string | undefined {
    const own = (roleForms[index].errors as Record<string, string>)[
        `roles.0.${field}`
    ];
    const bulk = (bulkForm.errors as Record<string, string>)[
        `roles.${index}.${field}`
    ];

    return own ?? bulk;
}

function isRoleSaved(index: number): boolean {
    return roleForms[index].recentlySuccessful || bulkForm.recentlySuccessful;
}

function isRoleBusy(index: number): boolean {
    return roleForms[index].processing || bulkForm.processing;
}

// The reachable-model catalog, shared by every role picker. Loaded once after
// mount via a standalone request so the page renders instantly; the pickers
// stay usable (manual entry) while it streams in.
const availableModels = ref<ModelOption[]>([]);
const catalogLoaded = ref(false);
const catalog = useHttp({});

const noModelsAvailable = computed(
    () => catalogLoaded.value && availableModels.value.length === 0,
);

onMounted(() => {
    catalog.get(ProviderController.models.url(), {
        onSuccess: (response) => {
            availableModels.value =
                (response as { models?: ModelOption[] }).models ?? [];
            catalogLoaded.value = true;
        },
        onError: () => {
            catalogLoaded.value = true;
        },
    });
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Model Roles',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Model Roles" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            title="Model Roles"
            description="Map each engine role to a model and its parameters. Pick from the models your provider key can reach — calls are routed by role, so you can tier strong and cheap models without code changes."
        />

        <!-- Save-all bar: surfaces only while an open loop exists (Zeigarnik),
             so the global action never competes with the per-section saves. -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="-translate-y-2 opacity-0"
        >
            <div
                v-if="hasUnsavedChanges"
                class="sticky top-0 z-20 -mx-1 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-background/80 px-4 py-3 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-background/60"
                data-test="model-roles-save-all-bar"
            >
                <p class="text-sm text-foreground">
                    <span class="font-medium">{{ dirtyCount }}</span>
                    {{ dirtyCount === 1 ? 'role has' : 'roles have' }} unsaved
                    changes
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-9"
                        :disabled="bulkForm.processing"
                        @click="discardAllChanges"
                    >
                        Discard
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="h-9"
                        :disabled="bulkForm.processing"
                        data-test="save-all-model-roles-button"
                        @click="saveAllRoles"
                    >
                        <Spinner v-if="bulkForm.processing" class="size-4" />
                        Save all
                    </Button>
                </div>
            </div>
        </Transition>

        <!-- Catalog couldn't load (usually a missing/invalid key): the pickers
             still accept a hand-typed slug, so this is a hint, not a blocker. -->
        <div
            v-if="noModelsAvailable"
            class="flex items-start gap-3 rounded-lg border border-border bg-card/40 p-4"
            data-test="model-roles-no-catalog-hint"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
            >
                <KeyRound class="size-5" />
            </span>
            <div class="min-w-0 space-y-1 text-sm">
                <p class="font-medium text-foreground">
                    No models to choose from yet
                </p>
                <p class="text-muted-foreground">
                    Add or check your key in
                    <Link
                        :href="providerSettings()"
                        class="font-medium text-foreground underline underline-offset-4"
                    >
                        Provider settings
                    </Link>
                    to load the searchable model list. You can still type a model
                    slug by hand below.
                </p>
            </div>
        </div>

        <!-- One independently savable card per engine role -->
        <div
            v-for="(form, index) in roleForms"
            :key="props.roles[index].role"
            class="space-y-4 rounded-lg border border-border bg-card/40 p-4"
            :data-test="`model-role-${props.roles[index].role}`"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-medium text-foreground">
                            {{ props.roles[index].label }}
                        </p>
                        <Badge
                            v-if="!props.roles[index].configured"
                            variant="secondary"
                        >
                            Not configured
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ props.roles[index].description }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <span
                        v-if="isRoleSaved(index)"
                        class="flex items-center gap-1 text-xs font-medium text-primary"
                        data-test="model-role-saved"
                    >
                        <Check class="size-3.5" />
                        Saved
                    </span>
                    <span
                        v-else-if="form.isDirty"
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <span class="size-1.5 rounded-full bg-amber-500" />
                        Unsaved
                    </span>

                    <Button
                        type="button"
                        size="sm"
                        class="h-9"
                        :disabled="isRoleBusy(index) || !form.isDirty"
                        :data-test="`save-model-role-${props.roles[index].role}`"
                        @click="saveRole(index)"
                    >
                        <Spinner v-if="isRoleBusy(index)" class="size-4" />
                        Save
                    </Button>
                </div>
            </div>

            <AlertError
                v-if="errorsForRole(index).length > 0"
                :errors="errorsForRole(index)"
                title="We couldn't save this role."
            />

            <div class="grid gap-2">
                <Label :for="`model_slug_${props.roles[index].role}`">Model</Label>
                <ModelCombobox
                    :id="`model_slug_${props.roles[index].role}`"
                    v-model="form.roles[0].model_slug"
                    :models="availableModels"
                    :loading="catalog.processing"
                    :invalid="!!errorForField(index, 'model_slug')"
                    placeholder="Search or paste a model slug"
                />
                <InputError :message="errorForField(index, 'model_slug')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label :for="`temperature_${props.roles[index].role}`">
                        Temperature
                    </Label>
                    <Input
                        :id="`temperature_${props.roles[index].role}`"
                        v-model.number="form.roles[0].temperature"
                        type="number"
                        step="0.1"
                        min="0"
                        max="2"
                        class="h-11"
                    />
                    <InputError :message="errorForField(index, 'temperature')" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`max_tokens_${props.roles[index].role}`">
                        Max tokens
                    </Label>
                    <Input
                        :id="`max_tokens_${props.roles[index].role}`"
                        v-model.number="form.roles[0].max_tokens"
                        type="number"
                        step="1"
                        min="1"
                        class="h-11"
                    />
                    <InputError :message="errorForField(index, 'max_tokens')" />
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <div class="space-y-0.5">
                    <Label :for="`is_active_${props.roles[index].role}`">
                        Active
                    </Label>
                    <p class="text-xs text-muted-foreground">
                        Turn off to stop routing this role to a model.
                    </p>
                </div>
                <Switch
                    :id="`is_active_${props.roles[index].role}`"
                    v-model="form.roles[0].is_active"
                />
            </div>
        </div>
    </div>
</template>
