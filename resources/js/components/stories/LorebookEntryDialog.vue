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
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import LorebookController from '@/actions/App/Http/Controllers/Stories/LorebookController';
import AlertError from '@/components/AlertError.vue';
import LorebookEntryFormFields from '@/components/stories/LorebookEntryFormFields.vue';
import type {ChapterOption, LorebookFormData} from '@/components/stories/LorebookEntryFormFields.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    /** Story slug used to build the Wayfinder URLs. */
    storySlug: string;
    /** Chapters available for the optional reveal gate. */
    chapters: ChapterOption[];
    /** When set, the dialog edits this entry; otherwise it creates. */
    entry?: LorebookEntry | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<LorebookFormData>({
    title: '',
    keywords: [],
    content: '',
    min_reveal_chapter_id: null,
});

/**
 * Reset the form to the active entry (edit) or to blanks (create).
 */
function syncForm(): void {
    form.defaults({
        title: props.entry?.title ?? '',
        keywords: props.entry ? [...props.entry.keywords] : [],
        content: props.entry?.content ?? '',
        min_reveal_chapter_id: props.entry?.minRevealChapter?.id ?? null,
    });
    form.reset();
    form.clearErrors();
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
                    v-if="form.hasErrors"
                    :errors="Object.values(form.errors)"
                    title="We couldn't save this entry."
                />

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
