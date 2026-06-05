<?php

namespace App\Models;

use App\Enums\ElapsedBucket;
use App\Enums\ElapsedSource;
use Database\Factories\SceneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scene - an ordered scene within a chapter (ADR 0009/0015).
 *
 * Carries its POV contract and declared in-world elapsed-time bucket.
 * Authoring-realm child of {@see Chapter}; unique per `(chapter_id, number)`.
 *
 * @property int $id
 * @property int $chapter_id
 * @property int $number
 * @property string $pov_mode
 * @property string $pov_anchor
 * @property string|null $tone
 * @property string|null $setting
 * @property array<int, string>|null $present_characters
 * @property ElapsedBucket $elapsed_bucket
 * @property ElapsedSource $elapsed_source
 */
#[Fillable([
    'chapter_id',
    'number',
    'pov_mode',
    'pov_anchor',
    'tone',
    'setting',
    'present_characters',
    'elapsed_bucket',
    'elapsed_source',
])]
class Scene extends Model
{
    /** @use HasFactory<SceneFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * @return HasMany<Beat, $this>
     */
    public function beats(): HasMany
    {
        return $this->hasMany(Beat::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'present_characters' => 'array',
            'elapsed_bucket' => ElapsedBucket::class,
            'elapsed_source' => ElapsedSource::class,
        ];
    }
}
