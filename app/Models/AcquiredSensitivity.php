<?php

namespace App\Models;

use App\Enums\SensitivityChannel;
use App\Enums\SensitivityTarget;
use App\Enums\SensitivityWeight;
use Database\Factories\AcquiredSensitivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AcquiredSensitivity - a runtime scar trigger picked up during play (ADR
 * 0005).
 *
 * The save-realm counterpart of the authored `sensitivities`; optionally links
 * to the `axis_deltas` rupture that installed it.
 *
 * @property int $id
 * @property int $session_id
 * @property int $character_id
 * @property string $detect
 * @property SensitivityTarget $target
 * @property array<string, mixed> $axes
 * @property SensitivityWeight $weight
 * @property SensitivityChannel $channel
 * @property int|null $installed_by_delta_id
 */
#[Fillable([
    'session_id',
    'character_id',
    'detect',
    'target',
    'axes',
    'weight',
    'channel',
    'installed_by_delta_id',
])]
class AcquiredSensitivity extends Model
{
    /** @use HasFactory<AcquiredSensitivityFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PlaySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class, 'session_id');
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<AxisDelta, $this>
     */
    public function installedByDelta(): BelongsTo
    {
        return $this->belongsTo(AxisDelta::class, 'installed_by_delta_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target' => SensitivityTarget::class,
            'axes' => 'array',
            'weight' => SensitivityWeight::class,
            'channel' => SensitivityChannel::class,
        ];
    }
}
