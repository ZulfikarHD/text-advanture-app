<?php

namespace App\Providers;

use App\Contracts\Llm\LlmClient;
use App\Services\Llm\OpenRouterClient;
use App\Services\Narrator\Blocks\BeatProducer;
use App\Services\Narrator\Blocks\LorebookProducer;
use App\Services\Narrator\Blocks\PovContractProducer;
use App\Services\Narrator\Blocks\ResumeAnchorProducer;
use App\Services\Narrator\Blocks\SceneStateProducer;
use App\Services\Narrator\NarratorPromptAssembler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The container tag the narrator block producers are grouped under.
     */
    private const string NARRATOR_BLOCK_PRODUCERS = 'narrator.block_producers';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // OpenRouter is the active LLM transport behind the provider-agnostic
        // interface (ADR 0017 §1); swapping providers is a rebind, not a caller
        // change.
        $this->app->bind(LlmClient::class, OpenRouterClient::class);

        $this->registerNarratorAssembler();
    }

    /**
     * Wire the registry-driven narrator prompt assembler (S-4.1.1).
     *
     * The producers are tagged so the assembler receives the lit-block set this
     * phase; later phases add producers (NPC blocks, MESH_AWARENESS,
     * DIRECTOR_STATE) by tagging them here — no assembler change.
     */
    private function registerNarratorAssembler(): void
    {
        $this->app->tag([
            PovContractProducer::class,
            BeatProducer::class,
            LorebookProducer::class,
            SceneStateProducer::class,
            ResumeAnchorProducer::class,
        ], self::NARRATOR_BLOCK_PRODUCERS);

        $this->app->singleton(
            NarratorPromptAssembler::class,
            fn ($app): NarratorPromptAssembler => new NarratorPromptAssembler(
                $app->tagged(self::NARRATOR_BLOCK_PRODUCERS),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
