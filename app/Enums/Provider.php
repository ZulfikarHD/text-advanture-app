<?php

namespace App\Enums;

/**
 * An LLM provider gateway a credential authenticates against (ADR 0017 §1).
 *
 * OpenRouter is the only gateway for now; the enum leaves room to add others
 * behind the same provider-agnostic client. Mirrors the
 * `provider_credentials.provider` DB enum.
 */
enum Provider: string
{
    case OpenRouter = 'openrouter';
}
