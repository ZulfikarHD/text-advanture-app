<script lang="ts">
/**
 * A character as presented to the workspace (server shape). `knowledgeBoundary`
 * and `foldedIdentity` are empty for the player.
 */
export type Character = {
    id: number;
    slug: string;
    name: string;
    isPlayer: boolean;
    baseOpacity: number;
    appearance: string | null;
    foldedIdentity: string | null;
    knowledgeBoundary: {
        knows: string[];
        doesNotKnow: string[];
    };
};
</script>

<script setup lang="ts">
/**
 * CharacterDialog - create/edit a minimal manual character (S-1.1.1 / S-1.1.2).
 *
 * One dialog serves both modes: passing `entry` switches it to edit. Uses the
 * `useForm` composable (not the `<Form>` component) because the knowledge
 * boundary is a pair of dynamic arrays bound to chip inputs. Submits to
 * CharacterController via Wayfinder and closes on success. No LLM call is made.
 */
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import CharacterFormFields from '@/components/stories/CharacterFormFields.vue';
import type { CharacterFormData } from '@/components/stories/CharacterFormFields.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import CharacterController from '@/actions/App/Http/Controllers/Stories/CharacterController';

const props = defineProps<{
    /** Story slug used to build the Wayfinder URLs. */
    storySlug: string;
    /** Whether a (new) player character is still allowed for this story. */
    canBePlayer: boolean;
    /** When set, the dialog edits this character; otherwise it creates. */
    entry?: Character | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<CharacterFormData>({
    name: '',
    is_player: false,
    appearance: '',
    base_opacity: 50,
    folded_identity: '',
    knowledge_boundary: { knows: [], does_not_know: [] },
});

/**
 * Reset the form to the active character (edit) or to blanks (create).
 */
function syncForm(): void {
    form.defaults({
        name: props.entry?.name ?? '',
        is_player: props.entry?.isPlayer ?? false,
        appearance: props.entry?.appearance ?? '',
        base_opacity: props.entry?.baseOpacity ?? 50,
        folded_identity: props.entry?.foldedIdentity ?? '',
        knowledge_boundary: {
            knows: props.entry ? [...props.entry.knowledgeBoundary.knows] : [],
            does_not_know: props.entry
                ? [...props.entry.knowledgeBoundary.doesNotKnow]
                : [],
        },
    });
    form.reset();
    form.clearErrors();
}

// Re-seed the form whenever the dialog opens so a stale draft never leaks
// between create and edit, or between two different characters.
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
            CharacterController.update.url({
                story: props.storySlug,
                character: props.entry.id,
            }),
            options,
        );

        return;
    }

    form.post(CharacterController.store.url({ story: props.storySlug }), options);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ props.entry ? 'Edit character' : 'New character' }}
                </DialogTitle>
                <DialogDescription>
                    Author a character by hand — no model call. A non-player needs
                    a folded identity and a knowledge boundary.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <CharacterFormFields
                    v-model:name="form.name"
                    v-model:is-player="form.is_player"
                    v-model:appearance="form.appearance"
                    v-model:base-opacity="form.base_opacity"
                    v-model:folded-identity="form.folded_identity"
                    v-model:knows="form.knowledge_boundary.knows"
                    v-model:does-not-know="form.knowledge_boundary.does_not_know"
                    :errors="form.errors"
                    :can-be-player="props.canBePlayer || (props.entry?.isPlayer ?? false)"
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
                        data-test="save-character"
                    >
                        {{ props.entry ? 'Save changes' : 'Create character' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
