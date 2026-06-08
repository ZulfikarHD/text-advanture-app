<script setup lang="ts">
/**
 * Stories/Edit - the Details tab of a story's workspace (S-1.1.2).
 *
 * Renders the shared StoryFormFields bound to StoryController@update via
 * Wayfinder. The per-story workspace layout supplies the title and tab nav, so
 * this page only renders the details form for the "Details" surface.
 */
import { Form, Head } from '@inertiajs/vue3';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import StoryFormFields from '@/components/stories/StoryFormFields.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import StoryController from '@/actions/App/Http/Controllers/Stories/StoryController';

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
            { title: 'Details', href: '' },
        ],
    },
});
</script>

<template>
    <Head :title="`${props.story.title} · Details`" />

    <section class="max-w-xl space-y-6">
        <Heading
            variant="small"
            title="Story details"
            description="Update your story's title, slug, and description."
        />

        <Form
            v-bind="StoryController.update.form({ story: props.story.slug })"
            :options="{ preserveScroll: true }"
            class="space-y-6"
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
    </section>
</template>
