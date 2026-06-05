<?php

namespace App\Enums;

/**
 * Outcome status of a logged model call (ADR 0017 §4).
 *
 * `Ok` completed, `Retry` failed but is being retried with backoff, `Failed`
 * exhausted retries and was surfaced to the caller. Mirrors the
 * `llm_calls.status` DB enum.
 */
enum LlmCallStatus: string
{
    case Ok = 'ok';
    case Retry = 'retry';
    case Failed = 'failed';
}
