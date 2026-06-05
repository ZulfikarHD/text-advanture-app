<?php

namespace App\Exceptions\Llm;

use RuntimeException;

/**
 * Base type for every failure raised by the LLM client subsystem (ADR 0017).
 *
 * Catching {@see LlmException} catches any client-side LLM error - an
 * unresolved role, an exhausted call, or a malformed structured payload - so
 * callers can fail closed without trusting a bad result.
 */
class LlmException extends RuntimeException {}
