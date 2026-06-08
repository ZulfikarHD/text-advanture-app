<?php

namespace App\Services\Llm\Data;

/**
 * A single model in the provider catalog, normalised for the model picker.
 *
 * Built from a raw provider `/models` entry (OpenRouter's catalog, which spans
 * every reachable model including the `anthropic/claude-*` Claude tier). Only
 * the fields the role picker needs are kept; pricing is intentionally omitted
 * (cost is displayed in Rupiah elsewhere, never raw provider USD).
 */
final readonly class ProviderModel
{
    public function __construct(
        public string $id,
        public string $name,
        public ?int $contextLength = null,
    ) {}

    /**
     * Build from a raw provider catalog entry, or null when it has no usable id.
     *
     * @param  array<string, mixed>  $model  A single `/models` `data[]` entry.
     */
    public static function fromArray(array $model): ?self
    {
        $id = data_get($model, 'id');

        if (! is_string($id) || $id === '') {
            return null;
        }

        $name = data_get($model, 'name');
        $contextLength = data_get($model, 'context_length');

        return new self(
            id: $id,
            name: is_string($name) && $name !== '' ? $name : $id,
            contextLength: is_numeric($contextLength) ? (int) $contextLength : null,
        );
    }

    /**
     * Shape the model for the client picker.
     *
     * @return array{id: string, name: string, contextLength: int|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contextLength' => $this->contextLength,
        ];
    }
}
