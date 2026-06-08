<script setup lang="ts">
/**
 * SceneDialog - create/edit a minimal manual scene (S-1.2.1).
 *
 * One dialog serves both modes: passing `entry` switches it to edit. A scene
 * carries its POV contract (mode + viewpoint anchor + optional tone) and the
 * cast present in it (character slugs). The viewpoint anchor is constrained to
 * the present cast, so toggling a character off prunes it. Submits to
 * StructureController via Wayfinder and closes on success. No LLM call.
 */
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import type {
    CharacterRef,
    PovOption,
    SceneFormData,
    StructureScene,
} from '@/components/stories/structure/types';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    /** Parent chapter id the scene belongs to. */
    chapterId: number;
    /** The story's cast for the present-cast + anchor selects. */
    characters: CharacterRef[];
    /** The POV vocabulary for the POV-mode select. */
    povOptions: PovOption[];
    /** Fallback POV mode preselected when creating. */
    defaultPov: string;
    /** When set, the dialog edits this scene; otherwise it creates. */
    entry?: StructureScene | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<SceneFormData>({
    pov_mode: props.defaultPov,
    pov_anchor: '',
    tone: '',
    present_characters: [],
});

// The viewpoint anchor must be one of the present cast.
const presentCast = computed<CharacterRef[]>(() =>
    props.characters.filter((character) =>
        form.present_characters.includes(character.slug),
    ),
);

/**
 * Reset the form to the active scene (edit) or to blanks (create).
 */
function syncForm(): void {
    form.defaults({
        pov_mode: props.entry?.povMode ?? props.defaultPov,
        pov_anchor: props.entry?.povAnchor ?? '',
        tone: props.entry?.tone ?? '',
        present_characters: props.entry
            ? [...props.entry.presentCharacters]
            : [],
    });
    form.reset();
    form.clearErrors();
}

// Re-seed the form whenever the dialog opens so a stale draft never leaks.
watch(open, (isOpen) => {
    if (isOpen) {
        syncForm();
    }
});

// Drop the anchor when its character leaves the present cast, so the saved
// contract always has a viewpoint that is actually in the scene.
watch(
    () => form.present_characters.slice(),
    (present) => {
        if (form.pov_anchor && !present.includes(form.pov_anchor)) {
            form.pov_anchor = '';
        }
    },
);

function togglePresent(slug: string, checked: boolean | 'indeterminate'): void {
    const next = new Set(form.present_characters);

    if (checked === true) {
        next.add(slug);
    } else {
        next.delete(slug);
    }

    form.present_characters = [...next];
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.entry) {
        form.put(
            StructureController.updateScene.url({
                story: props.storySlug,
                chapter: props.chapterId,
                scene: props.entry.id,
            }),
            options,
        );

        return;
    }

    form.post(
        StructureController.storeScene.url({
            story: props.storySlug,
            chapter: props.chapterId,
        }),
        options,
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ props.entry ? 'Edit scene' : 'New scene' }}
                </DialogTitle>
                <DialogDescription>
                    A scene sets the POV the narrator writes in and who is
                    present. The viewpoint character must be in the scene.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- POV mode -->
                <div class="grid gap-2">
                    <Label for="scene-pov-mode">Point of view</Label>
                    <Select v-model="form.pov_mode">
                        <SelectTrigger id="scene-pov-mode" class="h-11">
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
                    <InputError :message="form.errors.pov_mode" />
                </div>

                <!-- Present cast (character slugs) -->
                <fieldset class="grid gap-3 rounded-lg border border-border p-4">
                    <legend class="px-1 text-sm font-medium text-foreground">
                        Present characters
                    </legend>
                    <p class="text-xs text-muted-foreground">
                        Who is in the scene. Add at least one — the viewpoint
                        character is chosen from these.
                    </p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="character in props.characters"
                            :key="character.id"
                            class="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm"
                            :data-test="`present-${character.slug}`"
                        >
                            <Checkbox
                                :model-value="
                                    form.present_characters.includes(
                                        character.slug,
                                    )
                                "
                                @update:model-value="
                                    (checked) =>
                                        togglePresent(character.slug, checked)
                                "
                            />
                            <span class="line-clamp-1">{{ character.name }}</span>
                            <span
                                v-if="character.isPlayer"
                                class="ml-auto text-xs text-muted-foreground"
                            >
                                Player
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.present_characters" />
                </fieldset>

                <!-- POV anchor (viewpoint character, from present cast) -->
                <div class="grid gap-2">
                    <Label for="scene-pov-anchor">Viewpoint character</Label>
                    <Select v-model="form.pov_anchor" :disabled="presentCast.length === 0">
                        <SelectTrigger id="scene-pov-anchor" class="h-11">
                            <SelectValue
                                placeholder="Choose from the present cast"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="character in presentCast"
                                :key="character.id"
                                :value="character.slug"
                            >
                                {{ character.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.pov_anchor" />
                </div>

                <!-- Tone (optional) -->
                <div class="grid gap-2">
                    <Label for="scene-tone">Tone (optional)</Label>
                    <Input
                        id="scene-tone"
                        v-model="form.tone"
                        class="h-11"
                        autocomplete="off"
                        placeholder="tense"
                    />
                    <InputError :message="form.errors.tone" />
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
                        data-test="save-scene"
                    >
                        {{ props.entry ? 'Save changes' : 'Create scene' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
