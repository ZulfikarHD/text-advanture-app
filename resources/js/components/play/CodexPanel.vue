<script setup lang="ts">
/**
 * CodexPanel - the Writing/Play page's reference rail (E0.4).
 *
 * A read-only glance at where the playthrough sits and who/what is in the world:
 * the current position, the cast, and the lorebook titles. Shared by the desktop
 * rail and the mobile codex sheet so both stay in sync. Player-safe by design —
 * it never surfaces a character's private interiority, only names and slugs.
 */
import { BookMarked, Footprints, MapPin, Users } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

type CharacterRef = {
    id: number;
    name: string;
    slug: string;
    isPlayer: boolean;
};

type LoreRef = {
    id: number;
    title: string | null;
};

type Position = {
    chapterNumber: number | null;
    chapterTitle: string | null;
    sceneNumber: number | null;
    beatGoal: string | null;
};

defineProps<{
    characters: CharacterRef[];
    lore: LoreRef[];
    position: Position;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Where the playthrough is right now -->
        <section class="space-y-2">
            <h3
                class="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                <MapPin class="size-3.5" />
                Position
            </h3>
            <div class="rounded-lg bg-muted/50 p-3 text-sm">
                <p class="font-medium text-foreground">
                    Chapter {{ position.chapterNumber ?? '—' }}
                    <span v-if="position.sceneNumber" class="text-muted-foreground">
                        · Scene {{ position.sceneNumber }}
                    </span>
                </p>
                <p
                    v-if="position.chapterTitle"
                    class="truncate text-xs text-muted-foreground"
                >
                    {{ position.chapterTitle }}
                </p>
                <p
                    v-if="position.beatGoal"
                    class="mt-2 flex items-start gap-1.5 text-xs text-muted-foreground"
                >
                    <Footprints class="mt-0.5 size-3.5 shrink-0" />
                    <span>{{ position.beatGoal }}</span>
                </p>
            </div>
        </section>

        <!-- Cast -->
        <section class="space-y-2">
            <h3
                class="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                <Users class="size-3.5" />
                Cast
            </h3>
            <ul v-if="characters.length > 0" class="space-y-1">
                <li
                    v-for="character in characters"
                    :key="character.id"
                    class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm text-foreground"
                >
                    <span class="truncate">{{ character.name }}</span>
                    <Badge
                        v-if="character.isPlayer"
                        variant="secondary"
                        class="shrink-0"
                    >
                        You
                    </Badge>
                </li>
            </ul>
            <p v-else class="px-2 text-xs text-muted-foreground italic">
                No cast authored.
            </p>
        </section>

        <!-- World facts -->
        <section class="space-y-2">
            <h3
                class="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                <BookMarked class="size-3.5" />
                World
            </h3>
            <ul v-if="lore.length > 0" class="space-y-1">
                <li
                    v-for="entry in lore"
                    :key="entry.id"
                    class="truncate rounded-md px-2 py-1.5 text-sm text-foreground"
                >
                    {{ entry.title || 'Untitled entry' }}
                </li>
            </ul>
            <p v-else class="px-2 text-xs text-muted-foreground italic">
                No lorebook entries.
            </p>
        </section>
    </div>
</template>
