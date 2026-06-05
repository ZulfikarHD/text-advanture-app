<script setup lang="ts">
/**
 * Usage - the owner's LLM activity & cost log (S-5.3.1).
 *
 * The call list arrives as a deferred prop: the shell renders instantly and the
 * (potentially slow) owner-scoped query resolves behind a skeleton. Cost is the
 * provider-reported USD value (not Rupiah - the OpenRouter balance is USD);
 * timestamps render in WIB. Message bodies are never exposed here.
 */
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { Activity } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { formatUsdFromMicros, useFormat } from '@/composables/useFormat';
import { index } from '@/routes/usage';

type CallRow = {
    id: number;
    role: string;
    roleLabel: string;
    modelSlug: string;
    status: string;
    promptTokens: number | null;
    completionTokens: number | null;
    costMicrosUsd: number | null;
    latencyMs: number | null;
    createdAt: string | null;
    error: string | null;
};

type Paginator = {
    data: CallRow[];
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

defineProps<{
    calls?: Paginator;
}>();

const { formatDateTime } = useFormat();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Usage',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Usage" />

    <h1 class="sr-only">Usage</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Usage"
            description="Every model call made on your account, with token use, cost (USD), and latency. The log is append-only and visible only to you."
        />

        <Deferred data="calls">
            <!-- Loading state (Doherty): skeleton rows while the log query runs -->
            <template #fallback>
                <div
                    class="space-y-3 rounded-xl border border-border p-4"
                    data-test="usage-skeleton"
                >
                    <Skeleton v-for="row in 6" :key="row" class="h-10 w-full" />
                </div>
            </template>

            <!-- Empty state: no calls have been made yet -->
            <EmptyState
                v-if="!calls || calls.data.length === 0"
                :icon="Activity"
                title="No model calls yet"
                description="Once the engine starts making calls on your account, each one will appear here with its tokens, cost, and latency."
                data-test="usage-empty"
            />

            <!-- Success state: the owner's call log -->
            <div v-else class="space-y-4">
                <div
                    class="overflow-x-auto rounded-xl border border-border"
                    data-test="usage-table"
                >
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead class="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Role</th>
                                <th class="px-4 py-3 font-medium">Model</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">Tokens</th>
                                <th class="px-4 py-3 text-right font-medium">Cost (USD)</th>
                                <th class="px-4 py-3 text-right font-medium">Latency</th>
                                <th class="px-4 py-3 font-medium">Time (WIB)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="call in calls.data"
                                :key="call.id"
                                class="text-foreground"
                            >
                                <td class="px-4 py-3">{{ call.roleLabel }}</td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ call.modelSlug }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            call.status === 'failed'
                                                ? 'destructive'
                                                : 'secondary'
                                        "
                                    >
                                        {{ call.status }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ (call.promptTokens ?? 0) + (call.completionTokens ?? 0) || '—' }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ formatUsdFromMicros(call.costMicrosUsd) || '—' }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ call.latencyMs !== null ? `${call.latencyMs} ms` : '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                    {{ formatDateTime(call.createdAt) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination: partial-reload only the deferred list -->
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-muted-foreground">
                        Showing {{ calls.from ?? 0 }}–{{ calls.to ?? 0 }} of
                        {{ calls.total }}
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="calls.prev_page_url"
                            :href="calls.prev_page_url"
                            :only="['calls']"
                            preserve-scroll
                            class="inline-flex h-9 items-center rounded-md border border-border px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            Previous
                        </Link>
                        <Link
                            v-if="calls.next_page_url"
                            :href="calls.next_page_url"
                            :only="['calls']"
                            preserve-scroll
                            class="inline-flex h-9 items-center rounded-md border border-border px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </Deferred>
    </div>
</template>
