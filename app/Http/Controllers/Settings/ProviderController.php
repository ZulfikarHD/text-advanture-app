<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Provider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProviderCredentialUpdateRequest;
use App\Services\Llm\ConnectionTester;
use App\Services\ProviderCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the authenticated user's LLM-provider API key (S-5.1.1 / S-5.1.3)
 * and the live connection test (S-5.1.2).
 *
 * Only ever exposes the masked key to the client - the raw key is encrypted at
 * rest and never serialized. All work is delegated to
 * {@see ProviderCredentialService} (owner-scoped) and {@see ConnectionTester}.
 */
class ProviderController extends Controller
{
    public function __construct(
        private readonly ProviderCredentialService $credentials,
        private readonly ConnectionTester $connectionTester,
    ) {}

    /**
     * Show the provider key management page.
     */
    public function edit(Request $request): Response
    {
        $credential = $this->credentials->for($request->user());

        return Inertia::render('engine/Provider', [
            'provider' => Provider::OpenRouter->value,
            'defaultBaseUrl' => config('services.openrouter.base_url'),
            'credential' => $credential === null ? null : [
                'maskedKey' => $credential->masked_key,
                'baseUrl' => $credential->base_url,
                'updatedAtForHumans' => $credential->updated_at?->diffForHumans(),
            ],
        ]);
    }

    /**
     * Store or replace the user's provider API key.
     */
    public function update(ProviderCredentialUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->credentials->store(
            $request->user(),
            $validated['api_key'],
            Provider::OpenRouter,
            $validated['base_url'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Provider key saved.')]);

        return to_route('provider.edit');
    }

    /**
     * Remove the user's stored provider API key.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->credentials->forget($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Provider key removed.')]);

        return to_route('provider.edit');
    }

    /**
     * Test the stored key against the provider (S-5.1.2).
     *
     * Answers a standalone client request (Inertia `useHttp`) with the test
     * outcome - pass with reachable models or fail with the provider's reason -
     * so the user knows the key works before relying on it. This is key
     * validation only; it never returns the key and is not logged to `llm_calls`.
     *
     * @return JsonResponse The connection-test result payload (no secrets). The
     *                      probe itself succeeds (200); the `ok` flag carries the
     *                      pass/fail verdict so the client renders one result panel.
     */
    public function test(Request $request): JsonResponse
    {
        $result = $this->connectionTester->test($request->user());

        return response()->json($result->toArray());
    }
}
