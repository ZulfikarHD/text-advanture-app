<?php

namespace App\Services\Narrator;

use App\Enums\BlockSection;

/**
 * One folded prompt block in an assembled narrator prompt (ADR 0016/0020).
 *
 * The immutable pairing of a registry row's identity (`key`, `label`,
 * `section`) with the deterministic body its producer folded for the turn.
 * Carried in order inside {@see AssembledPrompt}; the `section` decides whether
 * it renders into the system or user message.
 */
final readonly class AssembledBlock
{
    public function __construct(
        public string $key,
        public string $label,
        public BlockSection $section,
        public string $body,
    ) {}
}
