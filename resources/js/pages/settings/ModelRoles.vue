<script setup lang="ts">
/**
 * ModelRoles - the global role -> model mapping editor (S-5.2.2).
 *
 * Engine calls are routed by role, never a hard-coded slug; this screen edits
 * the app-wide global defaults so an author can tier strong/cheap models
 * without code changes. Per-story overrides arrive with story management later.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import { computed } from 'vue';
import ModelRoleController from '@/actions/App/Http/Controllers/Settings/ModelRoleController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/model-roles';

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

type Props = {
    roles: RoleRow[];
};

const props = defineProps<Props>();

const noneConfigured = computed(() => props.roles.every((role) => !role.configured));

const form = useForm({
    roles: props.roles.map((role) => ({
        role: role.role,
        model_slug: role.modelSlug,
        temperature: role.temperature,
        max_tokens: role.maxTokens,
        is_active: role.isActive,
    })),
});

function submit(): void {
    form.put(ModelRoleController.update.url(), { preserveScroll: true });
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Model roles',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Model roles" />

    <h1 class="sr-only">Model roles</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Model roles"
            description="Map each engine role to an OpenRouter model and its parameters. Calls are routed by role, so you can tier strong and cheap models without code changes."
        />

        <!-- First-run hint: defaults are seeded later, so roles may be empty now -->
        <div
            v-if="noneConfigured"
            class="flex items-start gap-3 rounded-lg border border-border bg-card/40 p-4"
            data-test="model-roles-empty-hint"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
            >
                <Info class="size-5" />
            </span>
            <p class="text-sm text-muted-foreground">
                No model roles are configured yet. Set a model slug for each role
                the engine should use — for example
                <code class="font-mono">anthropic/claude-sonnet-4</code>.
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <AlertError
                v-if="form.hasErrors"
                :errors="Object.values(form.errors)"
                title="We couldn't save your model roles."
            />

            <!-- One card per engine role -->
            <div
                v-for="(row, index) in form.roles"
                :key="row.role"
                class="space-y-4 rounded-lg border border-border bg-card/40 p-4"
                :data-test="`model-role-${row.role}`"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm font-medium text-foreground">
                            {{ props.roles[index].label }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ props.roles[index].description }}
                        </p>
                    </div>
                    <Badge v-if="!props.roles[index].configured" variant="secondary">
                        Not configured
                    </Badge>
                </div>

                <div class="grid gap-2">
                    <Label :for="`model_slug_${row.role}`">Model slug</Label>
                    <Input
                        :id="`model_slug_${row.role}`"
                        v-model="row.model_slug"
                        class="h-11 font-mono"
                        autocomplete="off"
                        placeholder="anthropic/claude-sonnet-4"
                    />
                    <InputError :message="form.errors[`roles.${index}.model_slug`]" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="`temperature_${row.role}`">Temperature</Label>
                        <Input
                            :id="`temperature_${row.role}`"
                            v-model="row.temperature"
                            type="number"
                            step="0.1"
                            min="0"
                            max="2"
                            class="h-11"
                        />
                        <InputError :message="form.errors[`roles.${index}.temperature`]" />
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`max_tokens_${row.role}`">Max tokens</Label>
                        <Input
                            :id="`max_tokens_${row.role}`"
                            v-model="row.max_tokens"
                            type="number"
                            step="1"
                            min="1"
                            class="h-11"
                        />
                        <InputError :message="form.errors[`roles.${index}.max_tokens`]" />
                    </div>
                </div>

                <Label class="flex w-fit items-center gap-3">
                    <Checkbox v-model="row.is_active" />
                    <span class="text-sm text-foreground">Active</span>
                </Label>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    class="h-11"
                    :disabled="form.processing"
                    data-test="save-model-roles-button"
                >
                    Save model roles
                </Button>
            </div>
        </form>
    </div>
</template>
