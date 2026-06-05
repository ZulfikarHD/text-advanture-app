<?php

namespace App\Models;

use App\Enums\Provider;
use App\Models\Concerns\BelongsToOwner;
use Database\Factories\ProviderCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ProviderCredential - an owner's LLM-provider API key (S-5.1.1 / S-5.1.3).
 *
 * Owned via {@see BelongsToOwner} (owner global scope + stamped `user_id`), so
 * one user can never read another's key. `api_key` is encrypted at rest and
 * marked Hidden so it is never serialized to the client; the UI shows only the
 * computed {@see self::maskedKey()} built from the stored `last_four`. One row
 * per `(user_id, provider)`.
 *
 * @property int $id
 * @property int $user_id
 * @property Provider $provider
 * @property string $api_key
 * @property string|null $last_four
 * @property string|null $base_url
 * @property-read string|null $masked_key
 */
#[Fillable(['provider', 'api_key', 'last_four', 'base_url'])]
#[Hidden(['api_key'])]
class ProviderCredential extends Model
{
    /** @use HasFactory<ProviderCredentialFactory> */
    use BelongsToOwner, HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['masked_key'];

    /**
     * A safe-to-display mask of the key (never the full value).
     *
     * @return Attribute<string|null, never>
     */
    protected function maskedKey(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->last_four !== null
                ? str_repeat('•', 8).$this->last_four
                : null,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => Provider::class,
            'api_key' => 'encrypted',
        ];
    }
}
