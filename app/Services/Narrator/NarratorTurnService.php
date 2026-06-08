<?php

namespace App\Services\Narrator;

use App\Contracts\Llm\LlmClient;
use App\Enums\LlmRole;
use App\Enums\StateNode;
use App\Exceptions\Llm\LlmCallFailedException;
use App\Exceptions\Sessions\IllegalLoopTransitionException;
use App\Models\PlaySession;
use App\Services\Llm\Data\LlmRequest;
use App\Services\Llm\ModelRoleResolver;
use App\Services\SessionStateMachine;

/**
 * Runs one narrator prose call and feeds its handoff to the spine (S-4.2.1 /
 * S-4.2.2, ADR 0016 §4).
 *
 * The handoff producer the state machine was built to consume (PH-37): it
 * assembles the registry-driven narrator prompt (S-4.1.1), resolves the
 * `narrator_prose` model, and runs a single structured call that returns prose,
 * the handoff signal, and the inferred elapsed bucket together - so handoff
 * detection is the prose call's own output, not a separate classifier pass.
 *
 * The LLM call runs *before* any loop transition, so a malformed or failed call
 * surfaces (it bubbles {@see LlmCallFailedException}) without advancing the spine
 * - the loop never trusts an unparseable result (S-4.2.2). Only a validated
 * result advances: the session enters the loop if needed, then routes by the
 * structured handoff. This phase the result is returned to the caller; persisting
 * the prose into the scene log is S-5.2.1 and the visible reader is S-5.4.1.
 */
class NarratorTurnService
{
    public function __construct(
        private readonly NarratorPromptAssembler $assembler,
        private readonly ModelRoleResolver $roleResolver,
        private readonly LlmClient $llm,
        private readonly SessionStateMachine $stateMachine,
    ) {}

    /**
     * Run a narrator turn for a save and advance the spine by its handoff.
     *
     * @param  PlaySession  $session  The save to narrate (must be at session_start or narrator_turn).
     * @return NarratorTurnResult The validated prose, handoff, and elapsed bucket.
     *
     * @throws IllegalLoopTransitionException When the save is not awaiting a narrator turn.
     * @throws LlmCallFailedException When the prose call fails or never conforms within the retry bound (S-4.2.2).
     */
    public function run(PlaySession $session): NarratorTurnResult
    {
        // A narrator turn is only valid entering the loop or awaiting the
        // narrator; fail closed before spending an LLM call otherwise.
        if (! in_array($session->state_node, [StateNode::SessionStart, StateNode::NarratorTurn], true)) {
            throw IllegalLoopTransitionException::from($session->state_node, 'narratorTurn');
        }

        $result = $this->runProseCall($session);

        // Advance only after a validated result: enter the loop if this is the
        // opening turn, then route the spine by the structured handoff. A failed
        // call threw above, so the loop is never advanced on bad output.
        if ($session->state_node === StateNode::SessionStart) {
            $this->stateMachine->begin($session);
        }

        $this->stateMachine->applyHandoff($session, $result->handoff);

        return $result;
    }

    /**
     * Assemble the prompt, run the structured prose call, and type the result.
     *
     * Runs with no loop side effects so the caller can advance the spine only on
     * success.
     *
     * @param  PlaySession  $session  The save being narrated.
     *
     * @throws LlmCallFailedException When the call fails or never conforms (S-4.2.2).
     */
    private function runProseCall(PlaySession $session): NarratorTurnResult
    {
        $story = $session->story()->firstOrFail();
        $profile = $this->roleResolver->resolve(LlmRole::NarratorProse, $story);

        $response = $this->llm->completeStructured(
            new LlmRequest(
                role: LlmRole::NarratorProse,
                modelSlug: $profile->model_slug,
                messages: $this->assembler->assemble($session)->messages(),
                owner: $story->owner()->firstOrFail(),
                params: $profile->params ?? [],
                story: $story,
                session: $session,
            ),
            NarratorProseSchema::definition(),
            NarratorProseSchema::NAME,
        );

        return NarratorTurnResult::fromParsed($response->parsed ?? []);
    }
}
