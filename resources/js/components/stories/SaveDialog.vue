<script lang="ts">
/**
 * The minimal shape a rename needs from a save (id + current name).
 */
export type RenameableSave = {
    id: number;
    name: string;
};
</script>

<script setup lang="ts">
/**
 * SaveDialog - name a new save or rename an existing one (S-2.1.2).
 *
 * One dialog serves both modes: passing `save` switches it to rename. Create
 * mode prefills the suggested "Playthrough N" name (editable, optional - the
 * server falls back to the same default when blank). Uses `useForm` (not the
 * `<Form>` component) so the name field can be re-seeded per mode on open.
 * Submits to SessionController via Wayfinder and closes on success.
 */
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import SessionController from '@/actions/App/Http/Controllers/Stories/SessionController';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    /** Story slug used to build the Wayfinder URLs. */
    storySlug: string;
    /** When set, the dialog renames this save; otherwise it starts a new one. */
    save?: RenameableSave | null;
    /** Suggested default name for create mode (e.g. "Playthrough 3"). */
    suggestedName?: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<{ name: string }>({ name: '' });

/**
 * Re-seed the name field to the active save (rename) or the suggested default
 * (create) whenever the dialog opens, so a stale draft never leaks between
 * modes or between two different saves.
 */
function syncForm(): void {
    form.defaults({ name: props.save?.name ?? props.suggestedName ?? '' });
    form.reset();
    form.clearErrors();
}

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

    if (props.save) {
        form.put(
            SessionController.update.url({
                story: props.storySlug,
                playSession: props.save.id,
            }),
            options,
        );

        return;
    }

    form.post(SessionController.store.url({ story: props.storySlug }), options);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ props.save ? 'Rename save' : 'Start a new session' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        props.save
                            ? 'Give this playthrough a name you will recognise.'
                            : 'Fork this story into an independent save. Name it now, or keep the suggested default.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <AlertError
                    v-if="Object.keys(form.errors).length > 0"
                    :errors="Object.values(form.errors)"
                    title="We couldn't save this."
                />

                <div class="grid gap-2">
                    <Label for="save-name">Save name</Label>
                    <Input
                        id="save-name"
                        v-model="form.name"
                        class="h-11"
                        autocomplete="off"
                        :placeholder="props.suggestedName ?? 'Playthrough 1'"
                        data-test="save-name-input"
                    />
                    <InputError :message="form.errors.name" />
                </div>

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
                        data-test="save-dialog-submit"
                    >
                        {{ props.save ? 'Save name' : 'Start session' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
