<?php

namespace App\Enums;

/**
 * Scope of a model-role profile (ADR 0017 §2).
 *
 * `Global` rows (no story) are the defaults; `Story` rows override them for a
 * single story. Mirrors the `model_profiles.scope` DB enum.
 */
enum ModelScope: string
{
    case Global = 'global';
    case Story = 'story';
}
