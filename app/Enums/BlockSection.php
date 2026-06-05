<?php

namespace App\Enums;

/**
 * Where a prompt block renders within a prompt (ADR 0020).
 *
 * Whether the block belongs in the `System` instructions or the `User` turn
 * content. Mirrors the `prompt_blocks.section` DB enum.
 */
enum BlockSection: string
{
    case System = 'system';
    case User = 'user';
}
