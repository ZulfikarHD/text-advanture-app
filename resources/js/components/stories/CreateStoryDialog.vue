<script setup lang="ts">
/**
 * CreateStoryDialog - responsive create-story surface (S-1.1.1).
 *
 * Desktop: centered Dialog. Mobile: bottom Sheet. Wires the Inertia `<Form>`
 * component to StoryController@store via Wayfinder. Closes on success.
 */
import { Form } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import StoryController from '@/actions/App/Http/Controllers/Stories/StoryController';
import AlertError from '@/components/AlertError.vue';
import StoryFormFields from '@/components/stories/StoryFormFields.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const open = defineModel<boolean>('open', { default: false });
const formRef = ref<InstanceType<typeof Form> | null>(null);

watch(open, (isOpen) => {
    if (!isOpen && formRef.value) {
        formRef.value.reset();
        formRef.value.clearErrors();
    }
});

function onSuccess(): void {
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Create a new story</DialogTitle>
                <DialogDescription>
                    Give your story a title. A URL-safe slug will be derived
                    automatically if you leave it blank.
                </DialogDescription>
            </DialogHeader>

            <Form
                ref="formRef"
                v-bind="StoryController.store.form()"
                :options="{ preserveScroll: true, onSuccess }"
                reset-on-success
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <AlertError
                    v-if="Object.keys(errors).length > 0"
                    :errors="Object.values(errors)"
                    title="We couldn't create your story."
                />

                <StoryFormFields :errors="errors" />

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
                        :disabled="processing"
                        data-test="create-story-submit"
                    >
                        Create story
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
