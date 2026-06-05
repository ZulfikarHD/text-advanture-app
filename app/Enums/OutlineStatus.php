<?php

namespace App\Enums;

/**
 * Lifecycle status of a chapter outline (ADR 0019 §3).
 *
 * `Draft` is unprocessed author text, `Compiled` has produced chapters/scenes/
 * beats through the review gate, and `Manual` means beats were authored directly
 * with no compile. Mirrors the `chapter_outlines.status` DB enum.
 */
enum OutlineStatus: string
{
    case Draft = 'draft';
    case Compiled = 'compiled';
    case Manual = 'manual';
}
