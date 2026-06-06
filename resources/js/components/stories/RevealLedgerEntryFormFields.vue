<script lang="ts">
/**
 * Shape of the reveal-ledger entry form, shared by the create/edit dialog and
 * these fields. `who_knows` is an array of character slugs (the characters
 * exempt from the reveal clamp) and `reveal_chapter_id` anchors the reveal point
 * (ADR 0013 §3).
 */
export type RevealLedgerFormData = {
    fact: string;
    reveal_chapter_id: number | null;
    character_id: number | null;
    who_knows: string[];
    notes: string;
};

/** A selectable chapter for the required reveal point. */
export type ChapterOption = {
    id: number;
    number: number;
    title: string;
};

/** A selectable character for the optional "about" attribution. */
export type CharacterOption = {
    id: number;
    slug: string;
    name: string;
};

/** Validation errors keyed by form field. */
export type RevealLedgerFormErrors = Partial<
    Record<keyof RevealLedgerFormData, string>
>;
</script>

<script setup lang="ts">
/**
 * RevealLedgerEntryFormFields - shared inputs for the create/edit reveal-ledger
 * dialog (S-4.1.1).
 *
 * Renders the fact, the required reveal-chapter selector, the optional "about"
 * character selector (defaulting to a world secret), the who-knows slug chip
 * input, and an optional note. Each field is a two-way `defineModel`, so the
 * parent's `useForm` owns the data without this child mutating a prop. Both
 * selectors degrade to a disabled hint when the story has no chapters/characters
 * yet (they are authored in a later phase).
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
    errors: RevealLedgerFormErrors;
    /** The story's chapters; empty until structure is authored. */
    chapters: ChapterOption[];
    /** The story's cast; empty until characters are authored. */
    characters: CharacterOption[];
}>();

const fact = defineModel<string>('fact', { required: true });
const revealChapterId = defineModel<number | null>('revealChapterId', {
    required: true,
});
const characterId = defineModel<number | null>('characterId', {
    required: true,
});
const whoKnows = defineModel<string[]>('whoKnows', { required: true });
const notes = defineModel<string>('notes', { required: true });

const hasChapters = computed(() => props.chapters.length > 0);
const hasCharacters = computed(() => props.characters.length > 0);

// reka-ui Select works in strings; map null <-> a sentinel "world" option so the
// optional "about" character can be cleared back to a world secret.
const WORLD_SECRET = 'world';

const revealChapterValue = computed<string>({
    get: () =>
        revealChapterId.value === null ? '' : String(revealChapterId.value),
    set: (value) => {
        revealChapterId.value = value === '' ? null : Number(value);
    },
});

const characterValue = computed<string>({
    get: () =>
        characterId.value === null ? WORLD_SECRET : String(characterId.value),
    set: (value) => {
        characterId.value = value === WORLD_SECRET ? null : Number(value);
    },
});
</script>

<template>
    <!-- Fact (required short identifier) -->
    <div class="grid gap-2">
        <Label for="reveal-fact">Fact</Label>
        <Input
            id="reveal-fact"
            v-model="fact"
            class="h-11"
            autocomplete="off"
            placeholder="the_diagnosis"
        />
        <InputError :message="props.errors.fact" />
        <p class="text-xs text-muted-foreground">
            A short, stable name for the secret — the load-bearing fact that must
            not leak early.
        </p>
    </div>

    <!-- Reveal chapter (required reveal point) -->
    <div class="grid gap-2">
        <Label for="reveal-chapter">Reveal chapter</Label>
        <Select v-if="hasChapters" v-model="revealChapterValue">
            <SelectTrigger
                id="reveal-chapter"
                class="h-11"
                data-test="reveal-chapter-trigger"
            >
                <SelectValue placeholder="Select the chapter it becomes known" />
            </SelectTrigger>
            <SelectContent>
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
            data-test="reveal-chapter-empty"
        >
            <Info class="size-4 shrink-0" />
            Add chapters in Structure to set the chapter where this fact is
            revealed.
        </div>
        <InputError :message="props.errors.reveal_chapter_id" />
    </div>

    <!-- About character (optional; defaults to a world secret) -->
    <div class="grid gap-2">
        <Label for="reveal-character">About (optional)</Label>
        <Select v-if="hasCharacters" v-model="characterValue">
            <SelectTrigger
                id="reveal-character"
                class="h-11"
                data-test="reveal-character-trigger"
            >
                <SelectValue placeholder="World secret" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="WORLD_SECRET">
                    World secret (no character)
                </SelectItem>
                <SelectItem
                    v-for="character in props.characters"
                    :key="character.id"
                    :value="String(character.id)"
                >
                    {{ character.name }}
                </SelectItem>
            </SelectContent>
        </Select>
        <div
            v-else
            class="flex items-center gap-2 rounded-md border border-dashed border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
            data-test="reveal-character-empty"
        >
            <Info class="size-4 shrink-0" />
            No characters yet — this is saved as a world secret. Add characters to
            attribute it to someone.
        </div>
        <InputError :message="props.errors.character_id" />
    </div>

    <!-- Who knows before the reveal (character slugs exempt from the clamp) -->
    <div class="grid gap-2">
        <Label for="reveal-who-knows">Who knows before the reveal (optional)</Label>
        <TagsInput v-model="whoKnows" class="min-h-11">
            <TagsInputItem
                v-for="slug in whoKnows"
                :key="slug"
                :value="slug"
            >
                <TagsInputItemText />
                <TagsInputItemDelete />
            </TagsInputItem>
            <TagsInputInput
                id="reveal-who-knows"
                placeholder="Type a character slug, press Enter"
            />
        </TagsInput>
        <InputError :message="props.errors.who_knows" />
        <p class="text-xs text-muted-foreground">
            Character slugs (e.g. <code>vixia-archi</code>) that already know this
            fact — they stay exempt from the reveal clamp.
        </p>
    </div>

    <!-- Notes (optional author note) -->
    <div class="grid gap-2">
        <Label for="reveal-notes">Notes (optional)</Label>
        <Textarea
            id="reveal-notes"
            v-model="notes"
            class="min-h-24 resize-y"
            placeholder="Why this secret is load-bearing, where it pays off..."
        />
        <InputError :message="props.errors.notes" />
    </div>
</template>
