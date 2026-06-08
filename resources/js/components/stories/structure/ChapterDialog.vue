<script setup lang="ts">
/**
 * ChapterDialog - create/edit a minimal manual chapter (S-1.2.1).
 *
 * One dialog serves both modes: passing `entry` switches it to edit. A chapter
 * carries a title and a default POV mode; its number is system-managed. Submits
 * to StructureController via Wayfinder and closes on success. No LLM call.
 */
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import type {
    ChapterFormData,
    PovOption,
    StructureChapter,
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import StructureController from '@/actions/App/Http/Controllers/Stories/StructureController';

const props = defineProps<{
    /** Story slug used to build the Wayfinder URLs. */
    storySlug: string;
    /** The POV vocabulary for the default-POV select. */
    povOptions: PovOption[];
    /** The story's resolved default POV, preselected when creating. */
    defaultPov: string;
    /** When set, the dialog edits this chapter; otherwise it creates. */
    entry?: StructureChapter | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<ChapterFormData>({
    title: '',
    pov_default: props.defaultPov,
});

/**
 * Reset the form to the active chapter (edit) or to blanks (create).
 */
function syncForm(): void {
    form.defaults({
        title: props.entry?.title ?? '',
        pov_default: props.entry?.povDefault ?? props.defaultPov,
    });
    form.reset();
    form.clearErrors();
}

// Re-seed the form whenever the dialog opens so a stale draft never leaks
// between create and edit, or between two chapters.
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
            StructureController.updateChapter.url({
                story: props.storySlug,
                chapter: props.entry.id,
            }),
            options,
        );

        return;
    }

    form.post(
        StructureController.storeChapter.url({ story: props.storySlug }),
        options,
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ props.entry ? 'Edit chapter' : 'New chapter' }}
                </DialogTitle>
                <DialogDescription>
                    A chapter is the novel's backbone — give it a title and the
                    default point of view its scenes inherit.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- Title -->
                <div class="grid gap-2">
                    <Label for="chapter-title">Title</Label>
                    <Input
                        id="chapter-title"
                        v-model="form.title"
                        class="h-11"
                        autocomplete="off"
                        placeholder="Chapter 1"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <!-- Default POV -->
                <div class="grid gap-2">
                    <Label for="chapter-pov">Default point of view</Label>
                    <Select v-model="form.pov_default">
                        <SelectTrigger id="chapter-pov" class="h-11">
                            <SelectValue placeholder="Choose a POV" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="pov in props.povOptions"
                                :key="pov.value"
                                :value="pov.value"
                            >
                                {{ pov.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Scenes inherit this unless they re-declare their own POV.
                    </p>
                    <InputError :message="form.errors.pov_default" />
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
                        data-test="save-chapter"
                    >
                        {{ props.entry ? 'Save changes' : 'Create chapter' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
