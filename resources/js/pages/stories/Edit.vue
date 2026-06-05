<script setup lang="ts">
/**
 * Stories/Edit - dedicated edit page for a single story (S-1.1.2).
 *
 * Renders the shared StoryFormFields bound to StoryController@update via
 * Wayfinder. Breadcrumb links back to the Workspace. Room for per-story
 * settings/overview tabs in Sprint 8 (S-1.2.x).
 */
import { Form, Head } from '@inertiajs/vue3';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import StoryFormFields from '@/components/stories/StoryFormFields.vue';
import { Button } from '@/components/ui/button';
import StoryController from '@/actions/App/Http/Controllers/Stories/StoryController';
import { dashboard } from '@/routes';

type StoryData = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
};

const props = defineProps<{
    story: StoryData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Workspace', href: dashboard() },
            {
                title: 'Edit story',
                href: '',
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit · ${props.story.title}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            :title="props.story.title"
            description="Update your story's title, slug, and description."
        />

        <Form
            v-bind="
                StoryController.update.form({ story: props.story.slug })
            "
            :options="{ preserveScroll: true }"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <AlertError
                v-if="Object.keys(errors).length > 0"
                :errors="Object.values(errors)"
                title="We couldn't save your changes."
            />

            <StoryFormFields :errors="errors" :defaults="props.story" />

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    class="h-11"
                    :disabled="processing"
                    data-test="update-story-submit"
                >
                    Save changes
                </Button>
            </div>
        </Form>
    </div>
</template>
