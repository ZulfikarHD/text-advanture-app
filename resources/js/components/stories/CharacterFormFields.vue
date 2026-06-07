<script lang="ts">
/**
 * The knowledge boundary captured on a character's chapter-1 card — what the
 * character knows / does not know now. Mandatory for an NPC (Phase 2/4 leak
 * guards depend on it), empty for the player.
 */
export type CharacterKnowledgeBoundary = {
    knows: string[];
    does_not_know: string[];
};

/**
 * Shape of the character form, shared by the create/edit dialog and these
 * fields. Matches the StoreCharacterRequest payload.
 */
export type CharacterFormData = {
    name: string;
    is_player: boolean;
    appearance: string;
    base_opacity: number;
    folded_identity: string;
    knowledge_boundary: CharacterKnowledgeBoundary;
};

/** Validation errors keyed by form field. */
export type CharacterFormErrors = Partial<Record<keyof CharacterFormData, string>>;
</script>

<script setup lang="ts">
/**
 * CharacterFormFields - shared inputs for the create/edit character dialog
 * (S-1.1.1 / S-1.1.2).
 *
 * Renders name, appearance, base-opacity, and the player switch always; the
 * NPC-only folded identity and knowledge-boundary chip inputs appear only when
 * the character is not the player (a player carries appearance + base_opacity
 * only, no simulated interiority). Each field is a two-way `defineModel`, so the
 * parent's `useForm` owns the data without this child mutating a prop.
 */
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
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
    errors: CharacterFormErrors;
    /** When false, the player switch is locked off (another player exists). */
    canBePlayer: boolean;
}>();

const name = defineModel<string>('name', { required: true });
const isPlayer = defineModel<boolean>('isPlayer', { required: true });
const appearance = defineModel<string>('appearance', { required: true });
const baseOpacity = defineModel<number>('baseOpacity', { required: true });
const foldedIdentity = defineModel<string>('foldedIdentity', { required: true });
const knows = defineModel<string[]>('knows', { required: true });
const doesNotKnow = defineModel<string[]>('doesNotKnow', { required: true });

// The base-opacity column is an integer 0-100; proxy the number model through a
// string so the native number input stays controlled without a v-model modifier.
const baseOpacityProxy = computed<string>({
    get: () => String(baseOpacity.value ?? 0),
    set: (value) => {
        baseOpacity.value = value === '' ? 0 : Number(value);
    },
});
</script>

<template>
    <!-- Name -->
    <div class="grid gap-2">
        <Label for="character-name">Name</Label>
        <Input
            id="character-name"
            v-model="name"
            class="h-11"
            autocomplete="off"
            placeholder="Luna"
        />
        <InputError :message="props.errors.name" />
    </div>

    <!-- Player switch (mode selector) -->
    <div
        class="flex items-start justify-between gap-4 rounded-lg border border-border bg-muted/40 px-4 py-3"
    >
        <div class="space-y-0.5">
            <Label for="character-is-player" class="text-sm font-medium">
                This is the player character
            </Label>
            <p class="text-xs text-muted-foreground">
                The human supplies this character's behavior — appearance only, no
                simulated interiority. One player per story.
            </p>
        </div>
        <Switch
            id="character-is-player"
            v-model="isPlayer"
            :disabled="!props.canBePlayer && !isPlayer"
            data-test="character-is-player"
        />
    </div>
    <InputError :message="props.errors.is_player" />
    <p
        v-if="!props.canBePlayer && !isPlayer"
        class="-mt-2 text-xs text-muted-foreground"
    >
        This story already has a player character.
    </p>

    <!-- Appearance -->
    <div class="grid gap-2">
        <Label for="character-appearance">Appearance</Label>
        <Textarea
            id="character-appearance"
            v-model="appearance"
            class="min-h-20 resize-y"
            placeholder="small, sharp-eyed, fidgets with gloves"
        />
        <InputError :message="props.errors.appearance" />
    </div>

    <!-- Base opacity -->
    <div class="grid gap-2">
        <Label for="character-base-opacity">Base opacity</Label>
        <Input
            id="character-base-opacity"
            v-model="baseOpacityProxy"
            type="number"
            min="0"
            max="100"
            class="h-11"
            data-test="character-base-opacity"
        />
        <p class="text-xs text-muted-foreground">
            How guarded the character reads, 0–100 (0 = fully open, 100 =
            poker-faced). Seeds composure and legibility later.
        </p>
        <InputError :message="props.errors.base_opacity" />
    </div>

    <!-- NPC-only fields: a player has no simulated interiority -->
    <template v-if="!isPlayer">
        <!-- Folded identity -->
        <div class="grid gap-2">
            <Label for="character-folded-identity">Folded identity</Label>
            <Textarea
                id="character-folded-identity"
                v-model="foldedIdentity"
                class="min-h-24 resize-y"
                placeholder="a guarded classmate who deflects"
            />
            <p class="text-xs text-muted-foreground">
                A compact, spoiler-free sense of who they are right now.
            </p>
            <InputError :message="props.errors.folded_identity" />
        </div>

        <!-- Knowledge boundary (mandatory for an NPC) -->
        <fieldset class="grid gap-3 rounded-lg border border-border p-4">
            <legend class="px-1 text-sm font-medium text-foreground">
                Knowledge boundary
            </legend>
            <p class="text-xs text-muted-foreground">
                What this character knows and does not know right now. Required —
                it bounds what they can act on once the engine runs.
            </p>

            <div class="grid gap-2">
                <Label for="character-knows">Knows</Label>
                <TagsInput v-model="knows" class="min-h-11">
                    <TagsInputItem
                        v-for="fact in knows"
                        :key="fact"
                        :value="fact"
                    >
                        <TagsInputItemText />
                        <TagsInputItemDelete />
                    </TagsInputItem>
                    <TagsInputInput
                        id="character-knows"
                        placeholder="Add what they know, press Enter"
                    />
                </TagsInput>
            </div>

            <div class="grid gap-2">
                <Label for="character-does-not-know">Does not know</Label>
                <TagsInput v-model="doesNotKnow" class="min-h-11">
                    <TagsInputItem
                        v-for="fact in doesNotKnow"
                        :key="fact"
                        :value="fact"
                    >
                        <TagsInputItemText />
                        <TagsInputItemDelete />
                    </TagsInputItem>
                    <TagsInputInput
                        id="character-does-not-know"
                        placeholder="Add what they don't know, press Enter"
                    />
                </TagsInput>
            </div>

            <InputError :message="props.errors.knowledge_boundary" />
        </fieldset>
    </template>
</template>
