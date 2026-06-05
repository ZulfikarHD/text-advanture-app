<script setup lang="ts">
import { Form, Head, router, useHttp } from '@inertiajs/vue3';
import { CheckCircle2, KeyRound, PlugZap, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProviderController from '@/actions/App/Http/Controllers/Settings/ProviderController';
import AlertError from '@/components/AlertError.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useConfirm } from '@/composables/useConfirm';
import { edit } from '@/routes/provider';

type Credential = {
    maskedKey: string | null;
    baseUrl: string | null;
    updatedAtForHumans: string | null;
};

type ConnectionResult = {
    ok: boolean;
    reachableModelCount: number | null;
    sampleModels: string[];
    failureReason: string | null;
};

type Props = {
    provider: string;
    defaultBaseUrl: string;
    credential: Credential | null;
};

const props = defineProps<Props>();

const hasKey = computed(() => props.credential !== null);

const { confirm } = useConfirm();

// Connection test (S-5.1.2): a standalone JSON request that never reloads the
// page. The endpoint always answers 200; the `ok` flag carries the verdict.
const connection = useHttp({});
const testResult = ref<ConnectionResult | null>(null);

function testConnection(): void {
    testResult.value = null;

    connection.post(ProviderController.test.url(), {
        onSuccess: (data: ConnectionResult) => {
            testResult.value = data;
        },
        onError: () => {
            testResult.value = {
                ok: false,
                reachableModelCount: null,
                sampleModels: [],
                failureReason:
                    'The connection test could not be completed. Please try again.',
            };
        },
    });
}

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

            <div class="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    variant="secondary"
                    class="h-11"
                    :disabled="connection.processing"
                    data-test="test-connection-button"
                    @click="testConnection"
                >
                    <Spinner v-if="connection.processing" class="size-4" />
                    <PlugZap v-else class="size-4" />
                    {{ connection.processing ? 'Testing…' : 'Test connection' }}
                </Button>
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

            <!-- Connection test result: success (models reachable) vs failure (reason) -->
            <div
                v-if="testResult"
                role="status"
                aria-live="polite"
                data-test="connection-test-result"
            >
                <div
                    v-if="testResult.ok"
                    class="space-y-2 rounded-lg border border-border bg-background p-4"
                >
                    <p
                        class="flex items-center gap-2 text-sm font-medium text-foreground"
                    >
                        <CheckCircle2 class="size-4 text-primary" />
                        Connection successful —
                        {{ testResult.reachableModelCount }} models reachable
                    </p>
                    <div
                        v-if="testResult.sampleModels.length > 0"
                        class="flex flex-wrap gap-1.5"
                    >
                        <Badge
                            v-for="slug in testResult.sampleModels"
                            :key="slug"
                            variant="secondary"
                            class="font-mono"
                        >
                            {{ slug }}
                        </Badge>
                    </div>
                </div>
                <AlertError
                    v-else
                    :errors="[
                        testResult.failureReason ?? 'The connection test failed.',
                    ]"
                    title="Connection failed."
                />
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
