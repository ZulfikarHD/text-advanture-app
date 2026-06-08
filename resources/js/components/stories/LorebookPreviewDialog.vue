<script lang="ts">
/** A single entry the sample text triggered (server shape). */
export type PreviewEntry = {
    id: number;
    title: string | null;
    keywords: string[];
    matchedKeywords: string[];
};

/** A triggered entry that the reveal gate withholds at the previewed chapter. */
export type WithheldEntry = PreviewEntry & {
    minRevealChapter: { id: number; number: number; title: string } | null;
};

/** The preview endpoint result. */
export type PreviewResult = {
    triggered: PreviewEntry[];
    withheld: WithheldEntry[];
};
</script>

<script setup lang="ts">
/**
 * LorebookPreviewDialog - test which entries a sample text triggers (S-3.2.1).
 *
 * A read-only tuning aid: the author pastes a scene excerpt and, optionally, the
 * chapter they are previewing at; the dialog lists the entries whose keywords
 * match (triggered) and any that the reveal gate withholds at that chapter. It
 * posts to the preview endpoint via `useHttp` (a standalone request, no page
 * visit) and uses the same matching as runtime injection (ADR 0013 §5).
 */
import { useHttp } from '@inertiajs/vue3';
import { FlaskConical, Info, Lock, SearchX } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import type { ChapterOption } from '@/components/stories/LorebookEntryFormFields.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import LorebookController from '@/actions/App/Http/Controllers/Stories/LorebookController';

const props = defineProps<{
    /** Story slug used to build the Wayfinder preview URL. */
    storySlug: string;
    /** Chapters available for the optional reveal-gate clamp. */
    chapters: ChapterOption[];
}>();

const open = defineModel<boolean>('open', { default: false });

const preview = useHttp<{ sample_text: string; chapter_id: number | null }>({
    sample_text: '',
    chapter_id: null,
});

const result = ref<PreviewResult | null>(null);

const hasChapters = computed(() => props.chapters.length > 0);

// reka-ui Select works in strings; map null <-> a "none" sentinel so previewing
// "at any chapter" (no reveal clamp) is selectable.
const NO_CHAPTER = 'none';

const chapterValue = computed<string>({
    get: () =>
        preview.chapter_id === null ? NO_CHAPTER : String(preview.chapter_id),
    set: (value) => {
        preview.chapter_id = value === NO_CHAPTER ? null : Number(value);
    },
});

const hasResult = computed(() => result.value !== null);
const isEmptyResult = computed(
    () =>
        result.value !== null &&
        result.value.triggered.length === 0 &&
        result.value.withheld.length === 0,
);

// Reset the form and any prior result whenever the dialog reopens.
watch(open, (isOpen) => {
    if (isOpen) {
        preview.sample_text = '';
        preview.chapter_id = null;
        preview.clearErrors();
        result.value = null;
    }
});

function runPreview(): void {
    result.value = null;

    preview.post(LorebookController.preview.url({ story: props.storySlug }), {
        // useHttp types the response as `unknown`; this endpoint always returns
        // the PreviewResult shape (see docs/api/lorebook.md).
        onSuccess: (data) => {
            result.value = data as PreviewResult;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xl" data-test="lorebook-preview-dialog">
            <DialogHeader>
                <DialogTitle>Test keywords</DialogTitle>
                <DialogDescription>
                    Paste a sample scene to see which entries its keywords would
                    trigger — the same matching used at runtime.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="runPreview">
                <!-- Sample text -->
                <div class="grid gap-2">
                    <Label for="preview-sample">Sample text</Label>
                    <Textarea
                        id="preview-sample"
                        v-model="preview.sample_text"
                        class="min-h-28 resize-y"
                        placeholder="She adjusted her suppressor gloves before touching the Aether…"
                        data-test="preview-sample-text"
                    />
                    <InputError :message="preview.errors.sample_text" />
                </div>

                <!-- Previewed chapter (optional reveal-gate clamp) -->
                <div class="grid gap-2">
                    <Label for="preview-chapter">
                        Previewed chapter (optional)
                    </Label>
                    <Select v-if="hasChapters" v-model="chapterValue">
                        <SelectTrigger
                            id="preview-chapter"
                            class="h-11"
                            data-test="preview-chapter-trigger"
                        >
                            <SelectValue placeholder="Any chapter (no reveal clamp)" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NO_CHAPTER">
                                Any chapter (no reveal clamp)
                            </SelectItem>
                            <SelectItem
                                v-for="chapter in props.chapters"
                                :key="chapter.id"
                                :value="String(chapter.id)"
                            >
                                Chapter {{ chapter.number }} — {{ chapter.title }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div
                        v-else
                        class="flex items-center gap-2 rounded-md border border-dashed border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                    >
                        <Info class="size-4 shrink-0" />
                        Add chapters in Structure to test the reveal-chapter clamp.
                    </div>
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full sm:w-auto"
                    :disabled="preview.processing"
                    data-test="lorebook-preview-run"
                >
                    <Spinner v-if="preview.processing" class="size-4" />
                    <FlaskConical v-else class="size-4" />
                    {{ preview.processing ? 'Testing…' : 'Run preview' }}
                </Button>
            </form>

            <!-- Results: loading / empty / success states -->
            <div
                class="space-y-4"
                role="status"
                aria-live="polite"
                data-test="lorebook-preview-results"
            >
                <!-- Loading skeleton (Doherty: skeletons, not spinners, for waits) -->
                <div v-if="preview.processing" class="space-y-2">
                    <Skeleton class="h-16 w-full rounded-lg" />
                    <Skeleton class="h-16 w-full rounded-lg" />
                </div>

                <template v-else-if="hasResult">
                    <!-- Empty: text matched no entry -->
                    <div
                        v-if="isEmptyResult"
                        class="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border px-4 py-8 text-center"
                        data-test="preview-empty"
                    >
                        <SearchX class="size-6 text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">
                            No entries match this text. Adjust the keywords or the
                            sample to tune what triggers.
                        </p>
                    </div>

                    <template v-else>
                        <!-- Triggered entries -->
                        <section
                            v-if="result && result.triggered.length > 0"
                            class="space-y-2"
                            data-test="preview-triggered"
                        >
                            <h3 class="text-sm font-semibold text-foreground">
                                Triggered ({{ result.triggered.length }})
                            </h3>
                            <div
                                v-for="entry in result.triggered"
                                :key="entry.id"
                                class="rounded-lg border border-border bg-card p-3"
                                :data-test="`preview-triggered-${entry.id}`"
                            >
                                <p class="text-sm font-medium text-foreground">
                                    {{
                                        entry.title ||
                                        entry.matchedKeywords[0] ||
                                        'Untitled entry'
                                    }}
                                </p>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <Badge
                                        v-for="keyword in entry.matchedKeywords"
                                        :key="keyword"
                                        variant="secondary"
                                    >
                                        {{ keyword }}
                                    </Badge>
                                </div>
                            </div>
                        </section>

                        <!-- Withheld by reveal chapter -->
                        <section
                            v-if="result && result.withheld.length > 0"
                            class="space-y-2"
                            data-test="preview-withheld"
                        >
                            <h3 class="text-sm font-semibold text-foreground">
                                Withheld by reveal chapter
                                ({{ result.withheld.length }})
                            </h3>
                            <div
                                v-for="entry in result.withheld"
                                :key="entry.id"
                                class="rounded-lg border border-dashed border-border bg-muted/30 p-3"
                                :data-test="`preview-withheld-${entry.id}`"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        {{
                                            entry.title ||
                                            entry.matchedKeywords[0] ||
                                            'Untitled entry'
                                        }}
                                    </p>
                                    <Badge
                                        v-if="entry.minRevealChapter"
                                        variant="outline"
                                        class="shrink-0 gap-1"
                                    >
                                        <Lock class="size-3" />
                                        Chapter {{ entry.minRevealChapter.number }}
                                    </Badge>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Matches, but withheld until its minimum reveal
                                    chapter.
                                </p>
                            </div>
                        </section>
                    </template>
                </template>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    class="h-11"
                    @click="open = false"
                >
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
