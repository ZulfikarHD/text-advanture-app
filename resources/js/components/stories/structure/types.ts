/**
 * Shared types for the story Structure surface (S-1.2.1).
 *
 * The server ships the authored chapter -> scene -> beat tree (camelCased) plus
 * the story's cast and the POV vocabulary; the dialogs post back the snake_cased
 * payloads the form requests expect.
 */

/** A character reference used for the present cast + POV anchor selects. */
export type CharacterRef = {
    id: number;
    slug: string;
    name: string;
    isPlayer: boolean;
};

/** A POV mode option for the chapter/scene selects (value + author-facing copy). */
export type PovOption = {
    value: string;
    label: string;
    description: string;
};

/** A beat as presented to the workspace — its goal is the only field this phase. */
export type StructureBeat = {
    id: number;
    number: number;
    goal: string;
};

/** A scene with its POV contract, present cast (character slugs), and beats. */
export type StructureScene = {
    id: number;
    number: number;
    povMode: string;
    povAnchor: string;
    tone: string | null;
    presentCharacters: string[];
    beats: StructureBeat[];
};

/** A chapter with its default POV and scenes. `canDelete` is false while it anchors character cards. */
export type StructureChapter = {
    id: number;
    number: number;
    title: string;
    povDefault: string;
    canDelete: boolean;
    scenes: StructureScene[];
};

/** Chapter form payload — matches StoreChapterRequest. */
export type ChapterFormData = {
    title: string;
    pov_default: string;
};

/** Scene form payload — matches StoreSceneRequest. */
export type SceneFormData = {
    pov_mode: string;
    pov_anchor: string;
    tone: string;
    present_characters: string[];
};

/** Beat form payload — matches StoreBeatRequest. */
export type BeatFormData = {
    goal: string;
};
