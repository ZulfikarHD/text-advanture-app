<script lang="ts">
/**
 * A reveal-ledger entry as presented to the workspace (server shape).
 */
export type RevealLedgerEntry = {
    id: number;
    fact: string;
    character: { id: number; slug: string; name: string } | null;
    revealChapter: { id: number; number: number; title: string };
    whoKnows: string[];
    notes: string | null;
};
</script>

<script setup lang="ts">
/**
 * RevealLedgerEntryDialog - create/edit a reveal-ledger entry (S-4.1.1).
 *
 * One dialog serves both modes: passing `entry` switches it to edit. Uses the
 * `useForm` composable (not the `<Form>` component) because `who_knows` is a
 * dynamic array bound to the slug chip input. Submits to RevealLedgerController
 * via Wayfinder and closes on success.
 */
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import RevealLedgerController from '@/actions/App/Http/Controllers/Stories/RevealLedgerController';
import AlertError from '@/components/AlertError.vue';
import RevealLedgerEntryFormFields from '@/components/stories/RevealLedgerEntryFormFields.vue';
import type {
    CharacterOption,
    ChapterOption,
    RevealLedgerFormData,
} from '@/components/stories/RevealLedgerEntryFormFields.vue';
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
    /** Chapters available for the required reveal point. */
    chapters: ChapterOption[];
    /** Characters available for the optional "about" attribution. */
    characters: CharacterOption[];
    /** When set, the dialog edits this entry; otherwise it creates. */
    entry?: RevealLedgerEntry | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<RevealLedgerFormData>({
    fact: '',
    reveal_chapter_id: null,
    character_id: null,
    who_knows: [],
    notes: '',
});

/**
 * Reset the form to the active entry (edit) or to blanks (create).
 */
function syncForm(): void {
    form.defaults({
        fact: props.entry?.fact ?? '',
        reveal_chapter_id: props.entry?.revealChapter?.id ?? null,
        character_id: props.entry?.character?.id ?? null,
        who_knows: props.entry ? [...props.entry.whoKnows] : [],
        notes: props.entry?.notes ?? '',
    });
    form.reset();
    form.clearErrors();
}

const errors = computed<string[]>(() =>
    Object.values(form.errors).filter(
        (message): message is string => typeof message === 'string',
    ),
);

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
            RevealLedgerController.update.url({
                story: props.storySlug,
                revealLedgerEntry: props.entry.id,
            }),
            options,
        );

        return;
    }

    form.post(
        RevealLedgerController.store.url({ story: props.storySlug }),
        options,
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ props.entry ? 'Edit reveal-ledger entry' : 'New reveal-ledger entry' }}
                </DialogTitle>
                <DialogDescription>
                    Record a load-bearing secret and the chapter it becomes known,
                    so spoiler-safety is explicit rather than inferred.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <AlertError
                    v-if="errors.length > 0"
                    :errors="errors"
                    title="We couldn't save this entry."
                />

                <RevealLedgerEntryFormFields
                    v-model:fact="form.fact"
                    v-model:reveal-chapter-id="form.reveal_chapter_id"
                    v-model:character-id="form.character_id"
                    v-model:who-knows="form.who_knows"
                    v-model:notes="form.notes"
                    :errors="form.errors"
                    :chapters="props.chapters"
                    :characters="props.characters"
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
                        data-test="save-reveal-entry"
                    >
                        {{ props.entry ? 'Save changes' : 'Create entry' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
