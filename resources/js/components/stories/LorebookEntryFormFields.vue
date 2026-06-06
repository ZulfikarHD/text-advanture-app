<script lang="ts">
/**
 * Shape of the lorebook entry form, shared by the create/edit dialog and these
 * fields. `keywords` is an array because the runtime injection matches on
 * individual keyword strings (ADR 0013 §5).
 */
export type LorebookFormData = {
    title: string;
    keywords: string[];
    content: string;
    min_reveal_chapter_id: number | null;
};

/** A selectable chapter for the optional reveal-gate. */
export type ChapterOption = {
    id: number;
    number: number;
    title: string;
};

/** Validation errors keyed by form field. */
export type LorebookFormErrors = Partial<Record<keyof LorebookFormData, string>>;
</script>

<script setup lang="ts">
/**
 * LorebookEntryFormFields - shared inputs for the create/edit lorebook dialog
 * (S-3.1.1).
 *
 * Renders title, the keyword chip input, content, and the optional reveal-chapter
 * selector. Each field is a two-way `defineModel`, so the parent's `useForm`
 * owns the data without this child mutating a prop. The reveal selector degrades
 * to a disabled hint when the story has no chapters yet (they land in a later
 * phase).
 */
import { Info } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    TagsInput,
    TagsInputInput,
    TagsInputItem,
    TagsInputItemDelete,
    TagsInputItemText,
} from '@/components/ui/tags-input';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    /** Server-side validation errors keyed by field. */
    errors: LorebookFormErrors;
    /** The story's chapters; empty until structure is authored. */
    chapters: ChapterOption[];
}>();

const title = defineModel<string>('title', { required: true });
const keywords = defineModel<string[]>('keywords', { required: true });
const content = defineModel<string>('content', { required: true });
const minRevealChapterId = defineModel<number | null>('minRevealChapterId', {
    required: true,
});

const hasChapters = computed(() => props.chapters.length > 0);

// reka-ui Select works in strings; map null <-> a sentinel "none" option so the
// optional reveal-chapter can be cleared back to "always inject".
const NO_CHAPTER = 'none';

const revealChapterValue = computed<string>({
    get: () =>
        minRevealChapterId.value === null
            ? NO_CHAPTER
            : String(minRevealChapterId.value),
    set: (value) => {
        minRevealChapterId.value = value === NO_CHAPTER ? null : Number(value);
    },
});
</script>

<template>
    <!-- Title (optional) -->
    <div class="grid gap-2">
        <Label for="lorebook-title">Title (optional)</Label>
        <Input
            id="lorebook-title"
            v-model="title"
            class="h-11"
            autocomplete="off"
            placeholder="The Crystal Hollow"
        />
        <InputError :message="props.errors.title" />
    </div>

    <!-- Keywords (chip input; at least one required) -->
    <div class="grid gap-2">
        <Label for="lorebook-keywords">Keywords</Label>
        <TagsInput v-model="keywords" class="min-h-11">
            <TagsInputItem
                v-for="keyword in keywords"
                :key="keyword"
                :value="keyword"
            >
                <TagsInputItemText />
                <TagsInputItemDelete />
            </TagsInputItem>
            <TagsInputInput
                id="lorebook-keywords"
                placeholder="Type a keyword, press Enter"
            />
        </TagsInput>
        <InputError :message="props.errors.keywords" />
        <p class="text-xs text-muted-foreground">
            Entries are injected when the active scene mentions one of these
            keywords.
        </p>
    </div>

    <!-- Content -->
    <div class="grid gap-2">
        <Label for="lorebook-content">Content</Label>
        <Textarea
            id="lorebook-content"
            v-model="content"
            class="min-h-32 resize-y"
            placeholder="The Hollow is a sealed Aether sink beneath the old city..."
        />
        <InputError :message="props.errors.content" />
    </div>

    <!-- Minimum reveal chapter (optional gate) -->
    <div class="grid gap-2">
        <Label for="lorebook-reveal">Minimum reveal chapter (optional)</Label>
        <Select v-if="hasChapters" v-model="revealChapterValue">
            <SelectTrigger
                id="lorebook-reveal"
                class="h-11"
                data-test="lorebook-reveal-trigger"
            >
                <SelectValue placeholder="Always inject" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="NO_CHAPTER">Always inject</SelectItem>
                <SelectItem
                    v-for="chapter in props.chapters"
                    :key="chapter.id"
                    :value="String(chapter.id)"
                >
                    Chapter {{ chapter.number }} — {{ chapter.title }}
                </SelectItem>
            </SelectContent>
        </Select>
        <div
            v-else
            class="flex items-center gap-2 rounded-md border border-dashed border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
            data-test="lorebook-reveal-empty"
        >
            <Info class="size-4 shrink-0" />
            Add chapters in Structure to gate when this entry is revealed.
        </div>
        <InputError :message="props.errors.min_reveal_chapter_id" />
    </div>

    <!-- World-fact discipline reminder (S-3.1.2): proactive guidance; the save -->
    <!-- itself is soft-gated server-side if the content reads as interiority. -->
    <div
        class="flex items-start gap-2 rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
    >
        <Info class="mt-0.5 size-4 shrink-0" />
        <span>
            Lorebook entries are <strong>world facts</strong> — places, objects,
            and mechanisms. Keep a character's private feelings, secrets, or
            hidden intent out; those belong on the character cards.
        </span>
    </div>
</template>
