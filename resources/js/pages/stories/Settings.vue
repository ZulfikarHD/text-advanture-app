<script setup lang="ts">
/**
 * Stories/Settings - per-story settings: default POV + model-role overrides
 * (S-1.2.1).
 *
 * Lets one story deviate from the global defaults. The default POV is stored in
 * the story's settings; each engine role can override the global model profile.
 * Resolution order is per-story override -> global default, so a role with its
 * override switched off falls back to the global mapping. Rubric/tunable
 * overrides are deferred to a later sprint (E5.1).
 */
import { Head, useForm } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import StorySettingsController from '@/actions/App/Http/Controllers/Stories/StorySettingsController';

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

const props = defineProps<{
    story: StoryData;
    defaultPov: string;
    povOptions: PovOption[];
    roles: RoleRow[];
}>();

const form = useForm({
    default_pov: props.defaultPov,
    roles: props.roles.map((role) => ({
        role: role.role,
        override: role.override,
        model_slug: role.modelSlug,
        temperature: role.temperature,
        max_tokens: role.maxTokens,
        is_active: role.isActive,
    })),
});

function submit(): void {
    form.put(StorySettingsController.update.url({ story: props.story.slug }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`${props.story.title} · Settings`" />

    <form class="space-y-8" @submit.prevent="submit">
        <AlertError
            v-if="form.hasErrors"
            :errors="Object.values(form.errors)"
            title="We couldn't save your settings."
        />

        <!-- Default POV -->
        <section class="space-y-4">
            <Heading
                variant="small"
                title="Default point of view"
                description="The POV new scenes inherit. Individual scenes can still re-declare their own."
            />

            <div class="grid max-w-md gap-2">
                <Label for="default_pov">Default POV</Label>
                <Select id="default_pov" v-model="form.default_pov">
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
                <InputError :message="form.errors.default_pov" />
            </div>
        </section>

        <!-- Model-role overrides -->
        <section class="space-y-4">
            <Heading
                variant="small"
                title="Model-role overrides"
                description="Override the global model for any engine role, just for this story. Roles left unchecked use the global default."
            />

            <div
                v-for="(row, index) in form.roles"
                :key="row.role"
                class="space-y-4 rounded-lg border border-border bg-card/40 p-4"
                :data-test="`story-model-role-${row.role}`"
            >
                <!-- Role identity + override toggle -->
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm font-medium text-foreground">
                            {{ props.roles[index].label }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ props.roles[index].description }}
                        </p>
                    </div>
                    <Label class="flex shrink-0 items-center gap-2">
                        <Checkbox
                            v-model="row.override"
                            :data-test="`override-${row.role}`"
                        />
                        <span class="text-sm text-foreground">Override</span>
                    </Label>
                </div>

                <!-- Override fields (only when overriding) -->
                <div v-if="row.override" class="space-y-4">
                    <div class="grid gap-2">
                        <Label :for="`model_slug_${row.role}`">Model slug</Label>
                        <Input
                            :id="`model_slug_${row.role}`"
                            v-model="row.model_slug"
                            class="h-11 font-mono"
                            autocomplete="off"
                            placeholder="anthropic/claude-sonnet-4"
                        />
                        <InputError
                            :message="form.errors[`roles.${index}.model_slug`]"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`temperature_${row.role}`">
                                Temperature
                            </Label>
                            <Input
                                :id="`temperature_${row.role}`"
                                v-model="row.temperature"
                                type="number"
                                step="0.1"
                                min="0"
                                max="2"
                                class="h-11"
                            />
                            <InputError
                                :message="form.errors[`roles.${index}.temperature`]"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`max_tokens_${row.role}`">
                                Max tokens
                            </Label>
                            <Input
                                :id="`max_tokens_${row.role}`"
                                v-model="row.max_tokens"
                                type="number"
                                step="1"
                                min="1"
                                class="h-11"
                            />
                            <InputError
                                :message="form.errors[`roles.${index}.max_tokens`]"
                            />
                        </div>
                    </div>

                    <Label class="flex w-fit items-center gap-3">
                        <Checkbox v-model="row.is_active" />
                        <span class="text-sm text-foreground">Active</span>
                    </Label>
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

        <div class="flex items-center gap-4">
            <Button
                type="submit"
                class="h-11"
                :disabled="form.processing"
                data-test="save-story-settings-button"
            >
                Save settings
            </Button>
        </div>
    </form>
</template>
