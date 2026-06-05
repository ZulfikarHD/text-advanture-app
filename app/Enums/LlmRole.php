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
}
