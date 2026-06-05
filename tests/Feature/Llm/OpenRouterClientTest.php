<?php

namespace Tests\Feature\Llm;

use App\Contracts\Llm\LlmClient;
use App\Enums\LlmCallStatus;
use App\Enums\LlmRole;
use App\Models\LlmCall;
use App\Models\User;
use App\Services\Llm\Data\LlmRequest;
use App\Services\ProviderCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Happy-path tests for the OpenRouter LLM client (S-5.2.1, ADR 0017 §1).
 *
 * Proves a text call returns the model's content and logs an `Ok` row with
 * tokens + USD-micro cost, that structured calls parse + validate against the
 * schema, that the owner's key is sent as a Bearer token, and that the key is
 * never written into the `llm_calls` log.
 */
class OpenRouterClientTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk-or-v1-happypath-1234';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function ownerWithKey(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        (new ProviderCredentialService)->store($user, self::KEY);

        return $user;
    }

    public function test_complete_returns_text_and_logs_an_ok_call(): void
    {
        Http::fake([
            '*chat/completions' => Http::response([
                'id' => 'gen-abc',
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hello, traveler.']]],
                'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 40, 'cost' => 0.00042],
            ]),
        ]);

        $owner = $this->ownerWithKey();

        $response = app(LlmClient::class)->complete(new LlmRequest(
            role: LlmRole::NarratorProse,
            modelSlug: 'anthropic/claude-sonnet-4',
            messages: [['role' => 'user', 'content' => 'Begin.']],
            owner: $owner,
        ));

        $this->assertSame(LlmCallStatus::Ok, $response->status);
        $this->assertSame('Hello, traveler.', $response->text);
        $this->assertSame(120, $response->usage->promptTokens);
        $this->assertSame(40, $response->usage->completionTokens);
        $this->assertSame(420, $response->costMicrosUsd);

        $this->assertDatabaseHas('llm_calls', [
            'user_id' => $owner->id,
            'model_slug' => 'anthropic/claude-sonnet-4',
            'status' => LlmCallStatus::Ok->value,
            'prompt_tokens' => 120,
            'completion_tokens' => 40,
            'cost_micros_usd' => 420,
        ]);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer '.self::KEY));
    }

    public function test_the_owner_key_is_never_persisted_to_the_call_log(): void
    {
        Http::fake([
            '*chat/completions' => Http::response([
                'id' => 'gen-key',
                'choices' => [['message' => ['content' => 'ok']]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'cost' => 0.0],
            ]),
        ]);

        $owner = $this->ownerWithKey();

        app(LlmClient::class)->complete(new LlmRequest(
            role: LlmRole::NarratorProse,
            modelSlug: 'anthropic/claude-sonnet-4',
            messages: [['role' => 'user', 'content' => 'hi']],
            owner: $owner,
        ));

        $call = LlmCall::query()->firstOrFail();

        $this->assertNull($call->messages, 'Message bodies must stay null while the debug gate is off.');
        $this->assertStringNotContainsString(self::KEY, json_encode($call->getAttributes()));
    }

    public function test_complete_structured_validates_and_returns_the_parsed_payload(): void
    {
        Http::fake([
            '*chat/completions' => Http::response([
                'id' => 'gen-structured',
                'choices' => [['message' => ['content' => json_encode(['ok' => true, 'summary' => 'done'])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'cost' => 0.0001],
            ]),
        ]);

        $owner = $this->ownerWithKey();

        $schema = [
            'type' => 'object',
            'properties' => [
                'ok' => ['type' => 'boolean'],
                'summary' => ['type' => 'string'],
            ],
            'required' => ['ok', 'summary'],
        ];

        $response = app(LlmClient::class)->completeStructured(new LlmRequest(
            role: LlmRole::Recorder,
            modelSlug: 'anthropic/claude-sonnet-4',
            messages: [['role' => 'user', 'content' => 'record']],
            owner: $owner,
        ), $schema, 'record');

        $this->assertSame(LlmCallStatus::Ok, $response->status);
        $this->assertSame(['ok' => true, 'summary' => 'done'], $response->parsed);
        $this->assertSame(100, $response->costMicrosUsd);

        Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'response_format.type') === 'json_schema'
            && data_get($request->data(), 'response_format.json_schema.name') === 'record');
    }
}
