<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Provider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProviderCredentialUpdateRequest;
use App\Services\ProviderCredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the authenticated user's LLM-provider API key (S-5.1.1 / S-5.1.3).
 *
 * Only ever exposes the masked key to the client - the raw key is encrypted at
 * rest and never serialized. All work is delegated to
 * {@see ProviderCredentialService}, whose queries are owner-scoped.
 */
class ProviderController extends Controller
{
    public function __construct(private readonly ProviderCredentialService $credentials) {}

    /**
     * Show the provider key management page.
     */
    public function edit(Request $request): Response
    {
        $credential = $this->credentials->for($request->user());

        return Inertia::render('settings/Provider', [
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
}
