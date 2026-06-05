<?php

use App\Enums\Provider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-scoped: `provider_credentials` (S-5.1.1 / S-5.1.3, ADR 0017 §1).
 *
 * One LLM-provider API key per owner per provider. The key is encrypted at rest
 * (model `encrypted` cast) and never returned in plaintext - only `last_four`
 * is kept for a masked display. Owner-scoped like `stories` so one user can
 * never read another's key. This is a per-owner DB record rather than the
 * single `.env` key ADR 0017 §1 sketched - see PH-18.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', array_column(Provider::cases(), 'value'))
                ->default(Provider::OpenRouter->value);
            $table->text('api_key');
            $table->string('last_four', 8)->nullable();
            $table->string('base_url', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_credentials');
    }
};
