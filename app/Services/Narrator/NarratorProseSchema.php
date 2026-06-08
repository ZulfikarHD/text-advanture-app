<?php

namespace App\Services\Narrator;

use App\Contracts\Llm\LlmClient;
use App\Enums\ElapsedBucket;
use App\Enums\Handoff;

/**
 * The JSON-schema contract for a narrator prose call (S-4.2.1, ADR 0016 §4).
 *
 * The single source of the structured output the narrator turn requires: the
 * narrated `prose`, the `handoff` signal that routes the loop, and the inferred
 * `elapsed_bucket`. Passed to {@see LlmClient::completeStructured()},
 * which validates a returned payload against it - a non-conforming result
 * (including a `handoff` outside this phase's vocabulary) is retried then
 * surfaced, never trusted (S-4.2.2).
 *
 * `handoff` is deliberately narrowed to `player_moment | beat_complete` this
 * phase; `npc_moment` is a valid {@see Handoff} but its branch is not wired
 * until Phase 2, so the enum excludes it so the model can never route there.
 * `elapsed_bucket` is captured + validated now but only consumed by decay in
 * Phase 5 (recorded harmlessly until then).
 */
final class NarratorProseSchema
{
    /**
     * The schema name sent to the provider (response_format.json_schema.name).
     */
    public const string NAME = 'narrator_prose';

    /**
     * The handoff signals the narrator may route to this phase.
     *
     * `npc_moment` is excluded until its branch lights up in Phase 2.
     *
     * @return list<string>
     */
    public static function allowedHandoffs(): array
    {
        return [
            Handoff::PlayerMoment->value,
            Handoff::BeatComplete->value,
        ];
    }

    /**
     * The JSON schema the narrator prose call must conform to.
     *
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prose' => ['type' => 'string'],
                'handoff' => [
                    'type' => 'string',
                    'enum' => self::allowedHandoffs(),
                ],
                'elapsed_bucket' => [
                    'type' => 'string',
                    'enum' => array_column(ElapsedBucket::cases(), 'value'),
                ],
            ],
            'required' => ['prose', 'handoff', 'elapsed_bucket'],
            'additionalProperties' => false,
        ];
    }
}
