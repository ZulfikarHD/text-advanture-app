<script setup lang="ts">
/**
 * StoryFormFields - shared input fields for create and edit story forms.
 *
 * Renders title, slug, and description fields with labels and validation
 * errors. Used inside both the CreateStoryDialog and the Edit page so
 * field names and layout stay consistent.
 */

import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

defineProps<{
    /** Server-side validation errors keyed by field name. */
    errors: Record<string, string>;
    /** Default values for the fields (used when editing). */
    defaults?: {
        title?: string;
        slug?: string;
        description?: string | null;
    };
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label for="story-title">Title</Label>
        <Input
            id="story-title"
            name="title"
            class="h-11"
            :default-value="defaults?.title"
            required
            autocomplete="off"
            placeholder="The Crystal Hollow"
        />
        <InputError :message="errors.title" />
    </div>

    <div class="grid gap-2">
        <Label for="story-slug">Slug</Label>
        <Input
            id="story-slug"
            name="slug"
            class="h-11"
            :default-value="defaults?.slug"
            autocomplete="off"
            placeholder="Leave blank to derive from title"
        />
        <InputError :message="errors.slug" />
        <p class="text-xs text-muted-foreground">
            URL-safe identifier. Lowercase letters, numbers, and hyphens only.
        </p>
    </div>

    <div class="grid gap-2">
        <Label for="story-description">Description</Label>
        <Textarea
            id="story-description"
            name="description"
            class="min-h-24 resize-y"
            :default-value="defaults?.description ?? undefined"
            placeholder="A wandering archivist and her ward..."
        />
        <InputError :message="errors.description" />
    </div>
</template>
