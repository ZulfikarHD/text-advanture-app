<?php

namespace App\Enums;

/**
 * Narrative point-of-view a scene is written in (ADR 0009).
 *
 * A story declares a default POV in its settings; scenes inherit it from the
 * chapter outline and may re-declare per scene (`scenes.pov_mode`). Stored as a
 * plain string, so this enum is the canonical, author-facing value set rather
 * than a DB-enforced column. Resolution falls back to {@see self::default()}
 * until a global POV config home lands (PH-8).
 */
enum PovMode: string
{
    case FirstPerson = 'first_person';
    case SecondPerson = 'second_person';
    case ThirdLimited = 'third_limited';
    case ThirdOmniscient = 'third_omniscient';

    /**
     * A short human label for the POV mode (settings UI).
     */
    public function label(): string
    {
        return match ($this) {
            self::FirstPerson => 'First person',
            self::SecondPerson => 'Second person',
            self::ThirdLimited => 'Third person limited',
            self::ThirdOmniscient => 'Third person omniscient',
        };
    }

    /**
     * What the POV mode means for the narrator's contract (settings UI hint).
     */
    public function description(): string
    {
        return match ($this) {
            self::FirstPerson => 'Narrated from a single character\'s "I" perspective.',
            self::SecondPerson => 'Narrated as "you", casting the player as the subject.',
            self::ThirdLimited => 'Third person bound to one viewpoint character\'s knowledge.',
            self::ThirdOmniscient => 'Third person with access beyond any single character.',
        };
    }

    /**
     * The engine-wide default POV used when a story sets no override.
     */
    public static function default(): self
    {
        return self::ThirdLimited;
    }
}
