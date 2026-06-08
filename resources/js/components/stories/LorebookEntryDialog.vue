<script lang="ts">
/**
 * A lorebook entry as presented to the workspace (server shape).
 */
export type LorebookEntry = {
    id: number;
    title: string | null;
    keywords: string[];
    content: string;
    minRevealChapter: { id: number; number: number; title: string } | null;
};
</script>

<script setup lang="ts">
/**
 * LorebookEntryDialog - create/edit a lorebook entry (S-3.1.1).
 *
 * One dialog serves both modes: passing `entry` switches it to edit. Uses the
 * `useForm` composable (not the `<Form>` component) because `keywords` is a
 * dynamic array bound to the tag input. Submits to LorebookController via
 * Wayfinder and closes on success.
 */
import { Link, useForm } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed, watch } from 'vue';
import LorebookController from '@/actions/App/Http/Controllers/Stories/LorebookController';
import AlertError from '@/components/AlertError.vue';
import LorebookEntryFormFields from '@/components/stories/LorebookEntryFormFields.vue';
import type {ChapterOption, LorebookFormData} from '@/components/stories/LorebookEntryFormFields.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index as charactersIndex } from '@/routes/stories/characters';

/** The form data plus the dialog-only world-fact acknowledgement (S-3.1.2). */
type LorebookDialogForm = LorebookFormData & { acknowledge_interiority: boolean };

const props = defineProps<{
    /** Story slug used to build the Wayfinder URLs. */
    storySlug: string;
    /** Chapters available for the optional reveal gate. */
    chapters: ChapterOption[];
    /** When set, the dialog edits this entry; otherwise it creates. */
    entry?: LorebookEntry | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<LorebookDialogForm>({
    title: '',
    keywords: [],
    content: '',
    min_reveal_chapter_id: null,
    acknowledge_interiority: false,
});

/**
 * Reset the form to the active entry (edit) or to blanks (create).
 *
 * `acknowledge_interiority` always resets to false so each fresh attempt
 * re-runs the world-fact discipline check rather than inheriting a stale
 * acknowledgement.
 */
function syncForm(): void {
    form.defaults({
        title: props.entry?.title ?? '',
        keywords: props.entry ? [...props.entry.keywords] : [],
        content: props.entry?.content ?? '',
        min_reveal_chapter_id: props.entry?.minRevealChapter?.id ?? null,
        acknowledge_interiority: false,
    });
    form.reset();
    form.clearErrors();
}

// The interiority signal is a synthetic error key (not a form field), so read it
// loosely. Everything else flows through the generic error summary.
const interiorityError = computed<string | undefined>(
    () => (form.errors as Record<string, string | undefined>).interiority,
);

const otherErrors = computed<string[]>(() =>
    Object.entries(form.errors)
        .filter(([key]) => key !== 'interiority')
        .map(([, message]) => message as string),
);

/**
 * Override the world-fact discipline gate and save the entry as a world fact.
 */
function saveAnyway(): void {
    form.acknowledge_interiority = true;
    submit();
}

// Re-seed the form whenever the dialog opens so a stale draft never leaks
// between create and edit, or between two different entries.
watch(open, (isOpen) => {
    if (isOpen) {
        syncForm();
    }
});

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.entry) {
        form.put(
            LorebookController.update.url({
                story: props.storySlug,
                lorebookEntry: props.entry.id,
            }),
            options,
        );

        return;
    }

    form.post(LorebookController.store.url({ story: props.storySlug }), options);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ props.entry ? 'Edit lorebook entry' : 'New lorebook entry' }}
                </DialogTitle>
                <DialogDescription>
                    World facts injected into the story when their keywords appear
                    in a scene.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <AlertError
                    v-if="otherErrors.length > 0"
                    :errors="otherErrors"
                    title="We couldn't save this entry."
                />

                <!-- World-fact discipline (S-3.1.2): interiority detected. Soft -->
                <!-- gated — the author may override or move it to a character card. -->
                <Alert
                    v-if="interiorityError"
                    variant="warning"
                    data-test="lorebook-interiority-warning"
                >
                    <TriangleAlert class="size-4" />
                    <AlertTitle>This looks like character interiority</AlertTitle>
                    <AlertDescription class="space-y-3">
                        <p>{{ interiorityError }}</p>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <Button as-child variant="outline" class="h-10">
                                <Link :href="charactersIndex(props.storySlug)">
                                    Go to character cards
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                class="h-10"
                                :disabled="form.processing"
                                data-test="acknowledge-interiority"
                                @click="saveAnyway"
                            >
                                Save as world fact anyway
                            </Button>
                        </div>
                    </AlertDescription>
                </Alert>

                <LorebookEntryFormFields
                    v-model:title="form.title"
                    v-model:keywords="form.keywords"
                    v-model:content="form.content"
                    v-model:min-reveal-chapter-id="form.min_reveal_chapter_id"
                    :errors="form.errors"
                    :chapters="props.chapters"
                />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        class="h-11"
                        :disabled="form.processing"
                        data-test="save-lorebook-entry"
                    >
                        {{ props.entry ? 'Save changes' : 'Create entry' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
