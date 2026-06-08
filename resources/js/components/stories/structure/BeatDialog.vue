<script setup lang="ts">
/**
 * BeatDialog - create/edit a minimal manual beat (S-1.2.1).
 *
 * One dialog serves both modes: passing `entry` switches it to edit. The goal
 * is the only authored field this phase — it is the beat's satisfaction anchor
 * the narrator steers toward (intent / word budget arrive in Phase 4). Submits
 * to StructureController via Wayfinder and closes on success. No LLM call.
 */
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import type {
    BeatFormData,
    StructureBeat,
} from '@/components/stories/structure/types';
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
import { Textarea } from '@/components/ui/textarea';
import StructureController from '@/actions/App/Http/Controllers/Stories/StructureController';

const props = defineProps<{
    /** Story slug used to build the Wayfinder URLs. */
    storySlug: string;
    /** Parent chapter id (for the nested route). */
    chapterId: number;
    /** Parent scene id the beat belongs to. */
    sceneId: number;
    /** When set, the dialog edits this beat; otherwise it creates. */
    entry?: StructureBeat | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<BeatFormData>({
    goal: '',
});

/**
 * Reset the form to the active beat (edit) or to a blank (create).
 */
function syncForm(): void {
    form.defaults({ goal: props.entry?.goal ?? '' });
    form.reset();
    form.clearErrors();
}

// Re-seed the form whenever the dialog opens so a stale draft never leaks.
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
            StructureController.updateBeat.url({
                story: props.storySlug,
                chapter: props.chapterId,
                scene: props.sceneId,
                beat: props.entry.id,
            }),
            options,
        );

        return;
    }

    form.post(
        StructureController.storeBeat.url({
            story: props.storySlug,
            chapter: props.chapterId,
            scene: props.sceneId,
        }),
        options,
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ props.entry ? 'Edit beat' : 'New beat' }}
                </DialogTitle>
                <DialogDescription>
                    The goal is what the narrator steers this beat toward — the
                    anchor that decides when it is satisfied.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- Goal (the only load-bearing beat field this phase) -->
                <div class="grid gap-2">
                    <Label for="beat-goal">Goal</Label>
                    <Textarea
                        id="beat-goal"
                        v-model="form.goal"
                        class="min-h-20 resize-y"
                        placeholder="Luna and the player meet"
                    />
                    <InputError :message="form.errors.goal" />
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
                        data-test="save-beat"
                    >
                        {{ props.entry ? 'Save changes' : 'Create beat' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
