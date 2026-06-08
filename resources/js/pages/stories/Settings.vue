<script setup lang="ts">
/**
 * Stories/Settings - per-story settings: default POV + model-role overrides
 * (S-1.2.1).
 *
 * Lets one story deviate from the global defaults. The default POV is stored in
 * the story's settings; each engine role can override the global model profile.
 * Resolution order is per-story override -> global default, so a role with its
 * override switched off falls back to the global mapping.
 *
 * UX: the POV and each role override are independently savable sections (own
 * Save + dirty/saved state), so committing one change never means scrolling to
 * a shared footer (Fitts's Law + Goal-Gradient). Override models are chosen
 * from the provider's live catalog via a searchable picker (Hick's Law). A
 * "Save all" bar appears only while changes are pending.
 */
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import { Check, Info, KeyRound } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import ProviderController from '@/actions/App/Http/Controllers/Settings/ProviderController';
import StorySettingsController from '@/actions/App/Http/Controllers/Stories/StorySettingsController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ModelCombobox from '@/components/ModelCombobox.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { edit as providerSettings } from '@/routes/provider';

type PovOption = {
    value: string;
    label: string;
    description: string;
};

type GlobalFallback = {
    modelSlug: string;
    temperature: number;
    maxTokens: number;
    isActive: boolean;
    configured: boolean;
};

type RoleRow = {
    role: string;
    label: string;
    description: string;
    override: boolean;
    modelSlug: string;
    temperature: number;
    maxTokens: number;
    isActive: boolean;
    global: GlobalFallback;
};

type StoryData = {
    id: number;
    slug: string;
    title: string;
};

type ModelOption = {
    id: string;
    name: string;
    contextLength: number | null;
};

type RoleFormRow = {
    role: string;
    override: boolean;
    model_slug: string;
    temperature: number;
    max_tokens: number;
    is_active: boolean;
};

const props = defineProps<{
    story: StoryData;
    defaultPov: string;
    povOptions: PovOption[];
    roles: RoleRow[];
}>();

function updateUrl(): string {
    return StorySettingsController.update.url({ story: props.story.slug });
}

function initialRow(role: RoleRow): RoleFormRow {
    return {
        role: role.role,
        override: role.override,
        model_slug: role.modelSlug,
        temperature: role.temperature,
        max_tokens: role.maxTokens,
        is_active: role.isActive,
    };
}

// The default-POV section saves on its own.
const povForm = useForm({ default_pov: props.defaultPov });

// One independent form per role so each override saves on its own.
const roleForms = props.roles.map((role) =>
    useForm<{ roles: RoleFormRow[] }>({ roles: [initialRow(role)] }),
);

// Drives the "Save all" bar: submits POV + every role in card order in one
// request, so validation errors come back keyed by card index.
const bulkForm = useForm<{ default_pov: string; roles: RoleFormRow[] }>({
    default_pov: props.defaultPov,
    roles: [],
});

const dirtyCount = computed(
    () =>
        (povForm.isDirty ? 1 : 0) +
        roleForms.filter((form) => form.isDirty).length,
);
const hasUnsavedChanges = computed(() => dirtyCount.value > 0);

function savePov(): void {
    povForm.put(updateUrl(), {
        preserveScroll: true,
        onSuccess: () => {
            povForm.defaults();
            bulkForm.clearErrors();
        },
    });
}

function saveRole(index: number): void {
    roleForms[index].put(updateUrl(), {
        preserveScroll: true,
        onSuccess: () => {
            roleForms[index].defaults();
            bulkForm.clearErrors();
        },
    });
}

function saveAllChanges(): void {
    bulkForm
        .transform(() => ({
            default_pov: povForm.default_pov,
            roles: roleForms.map((form) => ({ ...form.roles[0] })),
        }))
        .put(updateUrl(), {
            preserveScroll: true,
            onSuccess: () => {
                povForm.defaults();
                roleForms.forEach((form) => form.defaults());
            },
        });
}

function discardAllChanges(): void {
    povForm.reset();
    povForm.clearErrors();
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

const povError = computed<string | undefined>(
    () =>
        povForm.errors.default_pov ??
        (bulkForm.errors as Record<string, string>).default_pov,
);
const isPovSaved = computed(
    () => povForm.recentlySuccessful || bulkForm.recentlySuccessful,
);
const isPovBusy = computed(() => povForm.processing || bulkForm.processing);

// The reachable-model catalog, shared by every override picker.
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
</script>

<template>
    <Head :title="`${props.story.title} · Settings`" />

    <div class="space-y-8">
        <!-- Save-all bar: surfaces only while an open loop exists (Zeigarnik). -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="-translate-y-2 opacity-0"
        >
            <div
                v-if="hasUnsavedChanges"
                class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-background/80 px-4 py-3 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-background/60"
                data-test="story-settings-save-all-bar"
            >
                <p class="text-sm text-foreground">
                    <span class="font-medium">{{ dirtyCount }}</span>
                    {{ dirtyCount === 1 ? 'change' : 'changes' }} not yet saved
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
                        data-test="save-all-story-settings-button"
                        @click="saveAllChanges"
                    >
                        <Spinner v-if="bulkForm.processing" class="size-4" />
                        Save all
                    </Button>
                </div>
            </div>
        </Transition>

        <!-- Default POV -->
        <section class="space-y-4">
            <div class="flex items-start justify-between gap-3">
                <Heading
                    variant="small"
                    title="Default point of view"
                    description="The POV new scenes inherit. Individual scenes can still re-declare their own."
                />
                <div class="flex shrink-0 items-center gap-3">
                    <span
                        v-if="isPovSaved"
                        class="flex items-center gap-1 text-xs font-medium text-primary"
                    >
                        <Check class="size-3.5" />
                        Saved
                    </span>
                    <span
                        v-else-if="povForm.isDirty"
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <span class="size-1.5 rounded-full bg-amber-500" />
                        Unsaved
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        class="h-9"
                        :disabled="isPovBusy || !povForm.isDirty"
                        data-test="save-story-pov-button"
                        @click="savePov"
                    >
                        <Spinner v-if="isPovBusy" class="size-4" />
                        Save
                    </Button>
                </div>
            </div>

            <div class="grid max-w-md gap-2">
                <Label for="default_pov">Default POV</Label>
                <Select id="default_pov" v-model="povForm.default_pov">
                    <SelectTrigger class="h-11" data-test="default-pov-trigger">
                        <SelectValue placeholder="Choose a POV" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in props.povOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="povError" />
            </div>
        </section>

        <!-- Model-role overrides -->
        <section class="space-y-4">
            <Heading
                variant="small"
                title="Model-role overrides"
                description="Override the global model for any engine role, just for this story. Roles left off use the global default."
            />

            <!-- Catalog couldn't load (usually a missing/invalid key): the
                 pickers still accept a hand-typed slug, so this is a hint. -->
            <div
                v-if="noModelsAvailable"
                class="flex items-start gap-3 rounded-lg border border-border bg-card/40 p-4"
                data-test="story-roles-no-catalog-hint"
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
                        to load the searchable model list. You can still type a
                        model slug by hand below.
                    </p>
                </div>
            </div>

            <div
                v-for="(form, index) in roleForms"
                :key="props.roles[index].role"
                class="space-y-4 rounded-lg border border-border bg-card/40 p-4"
                :data-test="`story-model-role-${props.roles[index].role}`"
            >
                <!-- Role identity + override toggle + per-section save -->
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm font-medium text-foreground">
                            {{ props.roles[index].label }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ props.roles[index].description }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <Label
                            class="flex items-center gap-2"
                            :for="`override_${props.roles[index].role}`"
                        >
                            <span class="text-xs text-muted-foreground">
                                Override
                            </span>
                            <Switch
                                :id="`override_${props.roles[index].role}`"
                                v-model="form.roles[0].override"
                                :data-test="`override-${props.roles[index].role}`"
                            />
                        </Label>

                        <div class="flex items-center gap-3">
                            <span
                                v-if="isRoleSaved(index)"
                                class="flex items-center gap-1 text-xs font-medium text-primary"
                                data-test="story-model-role-saved"
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
                                :data-test="`save-story-role-${props.roles[index].role}`"
                                @click="saveRole(index)"
                            >
                                <Spinner v-if="isRoleBusy(index)" class="size-4" />
                                Save
                            </Button>
                        </div>
                    </div>
                </div>

                <AlertError
                    v-if="errorsForRole(index).length > 0"
                    :errors="errorsForRole(index)"
                    title="We couldn't save this override."
                />

                <!-- Override fields (only when overriding) -->
                <div v-if="form.roles[0].override" class="space-y-4">
                    <div class="grid gap-2">
                        <Label :for="`model_slug_${props.roles[index].role}`">
                            Model
                        </Label>
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
                            <InputError
                                :message="errorForField(index, 'temperature')"
                            />
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
                            <InputError
                                :message="errorForField(index, 'max_tokens')"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <Label :for="`is_active_${props.roles[index].role}`">
                                Active
                            </Label>
                            <p class="text-xs text-muted-foreground">
                                Turn off to fall back to the global default for
                                this role.
                            </p>
                        </div>
                        <Switch
                            :id="`is_active_${props.roles[index].role}`"
                            v-model="form.roles[0].is_active"
                        />
                    </div>
                </div>

                <!-- Fallback summary (when not overriding) -->
                <div
                    v-else
                    class="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
                >
                    <template v-if="props.roles[index].global.configured">
                        Uses global default:
                        <code class="font-mono text-foreground">
                            {{ props.roles[index].global.modelSlug }}
                        </code>
                    </template>
                    <span v-else class="inline-flex items-center gap-1.5">
                        <Badge variant="secondary">No global default</Badge>
                        Set one under Model Roles, or override it here.
                    </span>
                </div>
            </div>
        </section>

        <!-- Deferred-scope note: rubric/tunable overrides land later (E5.1) -->
        <div
            class="flex items-start gap-3 rounded-lg border border-border bg-card/40 p-4"
            data-test="rubric-deferred-note"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
            >
                <Info class="size-5" />
            </span>
            <p class="text-sm text-muted-foreground">
                Severity-rubric and other tunable overrides are configured
                globally for now. Per-story tunable overrides arrive in a later
                sprint.
            </p>
        </div>
    </div>
</template>
