<script setup lang="ts">
/**
 * Stories/Characters - the story's character surface (S-1.1.1 / S-1.1.2).
 *
 * Lists the story's hand-authored cast and lets the author create, edit, and
 * delete them through a single responsive dialog — no model call. Each card
 * shows the character's appearance, whether it is the player, and (for an NPC)
 * its folded identity and knowledge-boundary size. Exactly one character may be
 * the player. Rendered inside the per-story workspace layout (tab nav + header).
 */
import { Head, router } from '@inertiajs/vue3';
import {
    Eye,
    Pencil,
    Plus,
    Trash2,
    User,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import CharacterDialog from '@/components/stories/CharacterDialog.vue';
import type { Character } from '@/components/stories/CharacterDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { dashboard } from '@/routes';
import CharacterController from '@/actions/App/Http/Controllers/Stories/CharacterController';

type StoryRef = {
    id: number;
    slug: string;
    title: string;
};

const props = defineProps<{
    story: StoryRef;
    characters: Character[];
}>();

const { confirm } = useConfirm();

const dialogOpen = ref(false);
const activeCharacter = ref<Character | null>(null);

// A story may have exactly one player; once one exists, the dialog locks the
// player switch off for every other character (and for new ones).
const canBePlayer = computed(
    () => !props.characters.some((character) => character.isPlayer),
);

function openCreate(): void {
    activeCharacter.value = null;
    dialogOpen.value = true;
}

function openEdit(character: Character): void {
    activeCharacter.value = character;
    dialogOpen.value = true;
}

async function deleteCharacter(character: Character): Promise<void> {
    const confirmed = await confirm({
        title: 'Delete this character?',
        description:
            'This permanently removes the character and its card. This cannot be undone.',
        confirmLabel: 'Delete character',
    });

    if (!confirmed) {
        return;
    }

    router.delete(
        CharacterController.destroy.url({
            story: props.story.slug,
            character: character.id,
        }),
        { preserveScroll: true },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Workspace', href: dashboard() },
            { title: 'Characters', href: '' },
        ],
    },
});
</script>

<template>
    <Head :title="`${props.story.title} · Characters`" />

    <!-- Heading + single primary action -->
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="space-y-1">
            <h2 class="text-base font-semibold text-foreground">Characters</h2>
            <p class="max-w-prose text-sm text-muted-foreground">
                The hand-authored cast a scene is about. Mark exactly one as the
                player; everyone else carries a folded identity and a knowledge
                boundary.
            </p>
        </div>
        <div v-if="props.characters.length > 0" class="shrink-0">
            <Button class="h-11" data-test="new-character" @click="openCreate">
                <Plus class="size-4" />
                New character
            </Button>
        </div>
    </header>

    <!-- Empty state: no characters yet -->
    <EmptyState
        v-if="props.characters.length === 0"
        :icon="Users"
        title="No characters yet"
        description="Author the cast a scene is about — by hand, with no model call. Start with the player, then add the characters they meet."
        data-test="characters-empty"
    >
        <template #action>
            <Button class="h-11" data-test="new-character" @click="openCreate">
                <Plus class="size-4" />
                New character
            </Button>
        </template>
    </EmptyState>

    <!-- Character list -->
    <div
        v-else
        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        data-test="characters-grid"
    >
        <Card
            v-for="character in props.characters"
            :key="character.id"
            class="flex flex-col"
            :data-test="`character-${character.id}`"
        >
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <span class="line-clamp-1">{{ character.name }}</span>
                    <Badge
                        v-if="character.isPlayer"
                        variant="default"
                        class="gap-1"
                    >
                        <User class="size-3" />
                        Player
                    </Badge>
                </CardTitle>
            </CardHeader>
            <CardContent class="flex-1 space-y-3">
                <p
                    v-if="character.appearance"
                    class="line-clamp-2 text-sm text-muted-foreground"
                >
                    {{ character.appearance }}
                </p>
                <p
                    v-if="!character.isPlayer && character.foldedIdentity"
                    class="line-clamp-2 text-sm text-foreground/80"
                >
                    {{ character.foldedIdentity }}
                </p>
                <div
                    v-if="!character.isPlayer"
                    class="flex flex-wrap gap-1.5 text-xs"
                >
                    <Badge variant="secondary" class="gap-1">
                        <Eye class="size-3" />
                        Knows {{ character.knowledgeBoundary.knows.length }}
                    </Badge>
                    <Badge variant="secondary">
                        Doesn't know
                        {{ character.knowledgeBoundary.doesNotKnow.length }}
                    </Badge>
                </div>
                <p v-else class="text-xs text-muted-foreground">
                    Appearance only — the human supplies this character's
                    behavior.
                </p>
            </CardContent>
            <CardFooter
                class="flex items-center justify-between gap-2 border-t border-border pt-4"
            >
                <Badge variant="outline">
                    Opacity {{ character.baseOpacity }}
                </Badge>
                <div class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9"
                        :data-test="`edit-character-${character.id}`"
                        @click="openEdit(character)"
                    >
                        <Pencil class="size-4" />
                        <span class="sr-only">Edit character</span>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9 text-destructive hover:text-destructive"
                        :data-test="`delete-character-${character.id}`"
                        @click="deleteCharacter(character)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Delete character</span>
                    </Button>
                </div>
            </CardFooter>
        </Card>
    </div>

    <!-- Create / edit dialog (single instance serves both modes) -->
    <CharacterDialog
        v-model:open="dialogOpen"
        :story-slug="props.story.slug"
        :can-be-player="canBePlayer"
        :entry="activeCharacter"
    />
</template>
