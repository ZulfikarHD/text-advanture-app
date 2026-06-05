<?php

namespace App\Enums;

/**
 * Engine role a model call is made for (ADR 0017 §2).
 *
 * Calls are routed by role, not a hard-coded model slug; each role resolves to
 * a model + params via `model_profiles` (per-story override -> global default).
 * Mirrors the `model_profiles.role` and `llm_calls.role` DB enums.
 */
enum LlmRole: string
{
    case NarratorProse = 'narrator_prose';
    case Recorder = 'recorder';
    case NpcMajor = 'npc_major';
    case NpcMinor = 'npc_minor';
    case Compiler = 'compiler';
    case Appraiser = 'appraiser';
    case BeatJudge = 'beat_judge';
    case NudgeCompiler = 'nudge_compiler';

    /**
     * A short human label for the role (model-profile settings UI).
     */
    public function label(): string
    {
        return match ($this) {
            self::NarratorProse => 'Narrator prose',
            self::Recorder => 'Recorder',
            self::NpcMajor => 'Major NPC',
            self::NpcMinor => 'Minor NPC',
            self::Compiler => 'Compiler',
            self::Appraiser => 'Appraiser',
            self::BeatJudge => 'Beat judge',
            self::NudgeCompiler => 'Nudge compiler',
        };
    }

    /**
     * What the role is used for, with its default tier hint (ADR 0017 §2).
     */
    public function description(): string
    {
        return match ($this) {
            self::NarratorProse => 'Writes the narrative prose for each turn. Strong tier.',
            self::Recorder => 'Extracts the structured record from the narration. Strong tier.',
            self::NpcMajor => 'Drives major characters (compile + act). Strong tier.',
            self::NpcMinor => 'Drives minor characters (compile + act). Cheap tier.',
            self::Compiler => 'Folds prompt blocks and compiles cards/outlines. Strong tier.',
            self::Appraiser => 'Proposes per-character appraisal deltas. Mid tier.',
            self::BeatJudge => 'Judges whether a beat goal is reached. Cheap tier.',
            self::NudgeCompiler => 'Turns beat intent into a leak-checked nudge. Strong tier.',
        };
    }
}
