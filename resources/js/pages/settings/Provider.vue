<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { KeyRound, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import ProviderController from '@/actions/App/Http/Controllers/Settings/ProviderController';
import AlertError from '@/components/AlertError.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import { edit } from '@/routes/provider';

type Credential = {
    maskedKey: string | null;
    baseUrl: string | null;
    updatedAtForHumans: string | null;
};

type Props = {
    provider: string;
    defaultBaseUrl: string;
    credential: Credential | null;
};

const props = defineProps<Props>();

const hasKey = computed(() => props.credential !== null);

const { confirm } = useConfirm();

async function removeKey(): Promise<void> {
    const confirmed = await confirm({
        title: 'Remove provider key?',
        description:
            'AI generation will stop working until you add a key again. Your stored key will be permanently deleted.',
        confirmLabel: 'Remove key',
    });

    if (!confirmed) {
        return;
    }

    router.delete(ProviderController.destroy.url(), { preserveScroll: true });
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Provider settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Provider settings" />

    <h1 class="sr-only">Provider settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="OpenRouter API key"
            description="Connect your own OpenRouter key to power AI generation. Your key is encrypted at rest and only ever shown masked."
        />

        <!-- Current key status: success (key on file) vs empty (no key yet) -->
        <div
            v-if="hasKey"
            class="space-y-4 rounded-lg border border-border bg-card/40 p-4"
            data-test="provider-key-status"
        >
            <div class="flex items-start gap-3">
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                >
                    <ShieldCheck class="size-5" />
                </span>
                <div class="min-w-0 space-y-1">
                    <p class="text-sm font-medium text-foreground">
                        Key on file
                    </p>
                    <p
                        class="font-mono text-sm break-all text-muted-foreground"
                        data-test="provider-masked-key"
                    >
                        {{ credential?.maskedKey }}
                    </p>
                    <p
                        v-if="credential?.updatedAtForHumans"
                        class="text-xs text-muted-foreground"
                    >
                        Updated {{ credential.updatedAtForHumans }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    class="h-11 text-destructive hover:text-destructive"
                    data-test="remove-provider-key-button"
                    @click="removeKey"
                >
                    Remove key
                </Button>
            </div>
        </div>

        <EmptyState
            v-else
            :icon="KeyRound"
            title="No provider key yet"
            description="Add your OpenRouter API key below to enable AI generation for your stories."
        />

        <!-- Add / replace key form: error + loading states handled inline -->
        <Form
            v-bind="ProviderController.update.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            :reset-on-error="['api_key']"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <AlertError
                v-if="Object.keys(errors).length > 0"
                :errors="Object.values(errors)"
                title="We couldn't save your key."
            />

            <div class="grid gap-2">
                <Label for="api_key">{{
                    hasKey ? 'Replace API key' : 'API key'
                }}</Label>
                <PasswordInput
                    id="api_key"
                    name="api_key"
                    class="h-11"
                    autocomplete="off"
                    placeholder="sk-or-v1-…"
                    data-test="provider-api-key-input"
                />
                <InputError :message="errors.api_key" />
            </div>

            <div class="grid gap-2">
                <Label for="base_url">Base URL (optional)</Label>
                <Input
                    id="base_url"
                    name="base_url"
                    type="url"
                    class="h-11"
                    autocomplete="off"
                    :placeholder="defaultBaseUrl"
                />
                <InputError :message="errors.base_url" />
                <p class="text-xs text-muted-foreground">
                    Leave blank to use the default gateway
                    ({{ defaultBaseUrl }}).
                </p>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    class="h-11"
                    :disabled="processing"
                    data-test="save-provider-key-button"
                >
                    {{ hasKey ? 'Replace key' : 'Save key' }}
                </Button>
            </div>
        </Form>
    </div>
</template>
