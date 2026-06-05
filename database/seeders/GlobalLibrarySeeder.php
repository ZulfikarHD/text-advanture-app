<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seed all five global, story-independent libraries (Sprint 6, Epic E6.1).
 *
 * Orchestrates the universal priors, register archetypes, character archetypes,
 * prompt-block registry, and default model profiles. Every child seeder is
 * idempotent (keyed on its natural key), so this is safe to run on a fresh
 * install or against an existing database.
 *
 * Run standalone with: `php artisan db:seed --class=GlobalLibrarySeeder`.
 */
class GlobalLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UniversalPriorSeeder::class,
            RegisterArchetypeSeeder::class,
            CharacterArchetypeSeeder::class,
            PromptBlockSeeder::class,
            ModelProfileSeeder::class,
        ]);
    }
}
