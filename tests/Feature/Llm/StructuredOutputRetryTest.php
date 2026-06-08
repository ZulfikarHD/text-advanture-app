<?php

namespace Tests\Feature\Llm;

use App\Contracts\Llm\LlmClient;
use App\Enums\LlmCallStatus;
use App\Enums\LlmRole;
use App\Exceptions\Llm\LlmStructuredOutputException;
use App\Models\User;
use App\Services\Llm\Data\LlmRequest;
use App\Services\ProviderCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Headline guarantee for structured output (S-5.2.3, ADR 0017 §3).
 *
 * A malformed structured payload is retried to the configured bound and, when
 * it never conforms, the call is recorded as `Failed` and surfaced as an
 * exception - the engine never receives unvalidated data as if it were valid.
 */
class StructuredOutputRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_structured_output_is_retried_then_fails_without_returning_data(): void
    {
        // Two attempts total (1 retry); fake sleep so backoff is instant.
        config(['services.openrouter.max_retries' => 1]);
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            '*chat/completions' => Http::response([
                'id' => 'gen-bad',
                'choices' => [['message' => ['content' => 'this is not json at all']]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5, 'cost' => 0.00001],
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        (new ProviderCredentialService)->store($user, 'sk-or-v1-retry-9999');

        $schema = [
            'type' => 'object',
            'properties' => ['verdict' => ['type' => 'string']],
            'required' => ['verdict'],
        ];

        $request = new LlmRequest(
            role: LlmRole::BeatJudge,
            modelSlug: 'cheap/model',
            messages: [['role' => 'user', 'content' => 'judge this beat']],
            owner: $user,
        );

        $returnedData = false;

        try {
            app(LlmClient::class)->completeStructured($request, $schema);
            $returnedData = true;
        } catch (LlmStructuredOutputException $e) {
            $this->assertStringContainsString('beat_judge', $e->getMessage());
        }

        $this->assertFalse($returnedData, 'A non-conforming structured payload must never be returned as data.');

        Http::assertSentCount(2);

        $this->assertDatabaseHas('llm_calls', [
            'user_id' => $user->id,
            'role' => LlmRole::BeatJudge->value,
            'status' => LlmCallStatus::Failed->value,
        ]);
        $this->assertDatabaseMissing('llm_calls', ['status' => LlmCallStatus::Ok->value]);
    }

    public function test_a_value_outside_a_declared_enum_is_retried_then_fails(): void
    {
        // A structurally valid payload whose enum value is out of vocabulary is
        // non-conforming, so it must be retried then surfaced - never trusted.
        config(['services.openrouter.max_retries' => 1]);
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            '*chat/completions' => Http::response([
                'id' => 'gen-enum',
                'choices' => [['message' => ['content' => json_encode(['handoff' => 'npc_moment'])]]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5, 'cost' => 0.00001],
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        (new ProviderCredentialService)->store($user, 'sk-or-v1-enum-9999');

        $schema = [
            'type' => 'object',
            'properties' => ['handoff' => ['type' => 'string', 'enum' => ['player_moment', 'beat_complete']]],
            'required' => ['handoff'],
        ];

        $request = new LlmRequest(
            role: LlmRole::NarratorProse,
            modelSlug: 'strong/model',
            messages: [['role' => 'user', 'content' => 'narrate this beat']],
            owner: $user,
        );

        $returnedData = false;

        try {
            app(LlmClient::class)->completeStructured($request, $schema, 'narrator_prose');
            $returnedData = true;
        } catch (LlmStructuredOutputException) {
            // Expected: an out-of-enum value is non-conforming.
        }

        $this->assertFalse($returnedData, 'An out-of-vocabulary enum value must never be returned as data.');

        Http::assertSentCount(2);

        $this->assertDatabaseHas('llm_calls', [
            'role' => LlmRole::NarratorProse->value,
            'status' => LlmCallStatus::Failed->value,
        ]);
        $this->assertDatabaseMissing('llm_calls', ['status' => LlmCallStatus::Ok->value]);
    }
}
