<?php

namespace App\Exceptions\Llm;

/**
 * Thrown when a structured call never returns a schema-conforming payload.
 *
 * After the bounded retries are spent on unparseable or non-conforming output,
 * the call is recorded as failed and this is raised - the engine never trusts
 * an unvalidated structured result (ADR 0017 §3, S-5.2.3).
 */
class LlmStructuredOutputException extends LlmCallFailedException {}
