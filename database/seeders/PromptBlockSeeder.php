<?php

namespace Database\Seeders;

use App\Enums\BlockAgent;
use App\Enums\BlockSection;
use App\Models\PromptBlock;
use Illuminate\Database\Seeder;

/**
 * Seed the prompt-block registry (S-6.1.3, ADR 0020 §3).
 *
 * The single source of truth for every block the assembler renders, taken
 * verbatim from the ADR 0020 / ADR 0016 inventory: agent, section, label,
 * source producers, order, and `leak_rules`. The `leak_rules` name only the six
 * existing guards (awareness_fold, knowledge_boundary, hedged_attribution,
 * own_perspective_only, omniscient_authoring, none) - the registry never invents
 * a guard, it names which one applies where.
 *
 * `LOREBOOK` appears for both agents with different leak rules (NPC is clamped
 * to known content; the narrator is omniscient). Because `prompt_blocks.key` is
 * unique, the narrator variant is keyed `LOREBOOK_NARRATOR` while still
 * rendering the `[LOREBOOK]` label (PLACEHOLDER_TRACKING PH-25). `purpose`,
 * `compile_instruction`, and `order_index` are authored from the glossary +
 * ADR 0016 inventory order (PH-25).
 *
 * Idempotent: keyed on `key`, safe to re-run.
 */
class PromptBlockSeeder extends Seeder
{
    /**
     * @var list<array{
     *     key: string, agent: BlockAgent, section: BlockSection, label: string,
     *     purpose: string, source_producers: list<array<string, string>>,
     *     compile_instruction: string, leak_rules: list<string>, order_index: int
     * }>
     */
    private const BLOCKS = [
        // --- NPC prompt ---------------------------------------------------
        [
            'key' => 'IDENTITY',
            'agent' => BlockAgent::Npc,
            'section' => BlockSection::System,
            'label' => '[IDENTITY]',
            'purpose' => 'Who the character is this chapter: the folded identity snapshot, clamped to what they currently know.',
            'source_producers' => [['adr' => '0013', 'table' => 'character_cards']],
            'compile_instruction' => 'State the character\'s identity and current self-knowledge for this chapter; never include facts past their knowledge boundary.',
            'leak_rules' => ['knowledge_boundary'],
            'order_index' => 1,
        ],
        [
            'key' => 'SELF',
            'agent' => BlockAgent::Npc,
            'section' => BlockSection::System,
            'label' => '[SELF]',
            'purpose' => "The character's own private internal truth (their real feelings and intentions).",
            'source_producers' => [['adr' => '0014', 'table' => 'internal_states']],
            'compile_instruction' => 'Give the character their own private internal state plainly; this is their own truth, no guard needed.',
            'leak_rules' => ['none'],
            'order_index' => 2,
        ],
        [
            'key' => 'SNAPSHOT',
            'agent' => BlockAgent::Npc,
            'section' => BlockSection::System,
            'label' => '[SNAPSHOT]',
            'purpose' => "This character's relationship edges toward the present others, folded with awareness.",
            'source_producers' => [['adr' => '0002', 'table' => 'edge_axes']],
            'compile_instruction' => 'Fold each edge value with its awareness; a capped feeling must never be stated plainly. Only this character\'s own edges.',
            'leak_rules' => ['awareness_fold', 'own_perspective_only'],
            'order_index' => 3,
        ],
        [
            'key' => 'MASKS',
            'agent' => BlockAgent::Npc,
            'section' => BlockSection::System,
            'label' => '[MASKS]',
            'purpose' => 'What the character is hiding and from whom (topic flags + masks).',
            'source_producers' => [
                ['adr' => '0002', 'table' => 'relationship_edges'],
                ['adr' => '0014', 'table' => 'internal_states'],
            ],
            'compile_instruction' => "List what this character conceals and from whom; only this character's own masks.",
            'leak_rules' => ['own_perspective_only'],
            'order_index' => 4,
        ],
        [
            'key' => 'DIRECTIVES',
            'agent' => BlockAgent::Npc,
            'section' => BlockSection::System,
            'label' => '[DIRECTIVES]',
            'purpose' => 'The resolved register grammar the character speaks in this turn.',
            'source_producers' => [['adr' => '0006', 'table' => 'registers']],
            'compile_instruction' => 'Translate the resolved register dimensions into concrete behavioral directives for the prose.',
            'leak_rules' => ['none'],
            'order_index' => 5,
        ],
        [
            'key' => 'NUDGE',
            'agent' => BlockAgent::Npc,
            'section' => BlockSection::System,
            'label' => '[NUDGE]',
            'purpose' => 'A leak-checked authoring nudge steering the character toward the beat intent.',
            'source_producers' => [['adr' => '0008', 'table' => 'nudges']],
            'compile_instruction' => 'Apply the compiled nudge as authoring intent; it is omniscient input and must already be clamped to the knowledge boundary before crossing.',
            'leak_rules' => ['omniscient_authoring', 'knowledge_boundary'],
            'order_index' => 6,
        ],
        [
            'key' => 'SCENE_RULES',
            'agent' => BlockAgent::Npc,
            'section' => BlockSection::System,
            'label' => '[SCENE RULES]',
            'purpose' => 'The scene\'s point-of-view and tone constraints.',
            'source_producers' => [['adr' => '0009', 'table' => 'scenes']],
            'compile_instruction' => 'State the scene tone and POV rules that bound the turn.',
            'leak_rules' => ['none'],
            'order_index' => 7,
        ],
        [
            'key' => 'LOREBOOK',
            'agent' => BlockAgent::Npc,
            'section' => BlockSection::System,
            'label' => '[LOREBOOK]',
            'purpose' => 'World facts matched by keyword, clamped to what this character knows.',
            'source_producers' => [['adr' => '0013', 'table' => 'lorebook_entries']],
            'compile_instruction' => 'Include only keyword-matched lore the character could know this chapter.',
            'leak_rules' => ['knowledge_boundary'],
            'order_index' => 8,
        ],
        [
            'key' => 'SCENE_EXCERPT',
            'agent' => BlockAgent::Npc,
            'section' => BlockSection::User,
            'label' => '[SCENE EXCERPT]',
            'purpose' => 'The recent narration surface the character is reacting to.',
            'source_producers' => [['adr' => '0010', 'table' => 'scene_summaries']],
            'compile_instruction' => "Present the projected, recorder-stripped surface; mental-state reads only as 'looks/seems', and only what this character could perceive.",
            'leak_rules' => ['hedged_attribution', 'knowledge_boundary'],
            'order_index' => 9,
        ],

        // --- Narrator prompt ----------------------------------------------
        [
            'key' => 'POV_CONTRACT',
            'agent' => BlockAgent::Narrator,
            'section' => BlockSection::System,
            'label' => '[POV CONTRACT]',
            'purpose' => 'The point-of-view contract the narration must honor.',
            'source_producers' => [['adr' => '0009', 'table' => 'scenes']],
            'compile_instruction' => 'State the POV contract for the scene.',
            'leak_rules' => ['none'],
            'order_index' => 1,
        ],
        [
            'key' => 'MESH_AWARENESS',
            'agent' => BlockAgent::Narrator,
            'section' => BlockSection::System,
            'label' => '[MESH-AWARENESS]',
            'purpose' => 'The full relationship mesh; the narrator is omniscient but its prose stays hedged.',
            'source_producers' => [['adr' => '0002', 'table' => 'edge_axes']],
            'compile_instruction' => "Carry the full mesh, but render mental-state reads only as 'looks/seems'; never state what a present character would not know.",
            'leak_rules' => ['hedged_attribution'],
            'order_index' => 2,
        ],
        [
            'key' => 'BEAT',
            'agent' => BlockAgent::Narrator,
            'section' => BlockSection::System,
            'label' => '[BEAT]',
            'purpose' => 'The omniscient beat document directing the turn.',
            'source_producers' => [['adr' => '0015', 'table' => 'beats']],
            'compile_instruction' => 'Apply the beat document as omniscient authoring direction for the turn.',
            'leak_rules' => ['omniscient_authoring'],
            'order_index' => 3,
        ],
        [
            'key' => 'DIRECTOR_STATE',
            'agent' => BlockAgent::Narrator,
            'section' => BlockSection::System,
            'label' => '[DIRECTOR STATE]',
            'purpose' => 'The engine clock and director state for the turn.',
            'source_producers' => [['adr' => '0015', 'table' => 'play_sessions']],
            'compile_instruction' => 'Summarize the director/engine clock state for pacing.',
            'leak_rules' => ['none'],
            'order_index' => 4,
        ],
        [
            'key' => 'LOREBOOK_NARRATOR',
            'agent' => BlockAgent::Narrator,
            'section' => BlockSection::System,
            'label' => '[LOREBOOK]',
            'purpose' => 'World facts matched by keyword; the narrator is omniscient, so no knowledge clamp applies.',
            'source_producers' => [['adr' => '0013', 'table' => 'lorebook_entries']],
            'compile_instruction' => 'Include keyword-matched lore relevant to the scene.',
            'leak_rules' => ['none'],
            'order_index' => 5,
        ],
        [
            'key' => 'SCENE_STATE',
            'agent' => BlockAgent::Narrator,
            'section' => BlockSection::System,
            'label' => '[SCENE STATE]',
            'purpose' => 'The running scene context-memory.',
            'source_producers' => [['adr' => '0015', 'table' => 'scene_summaries']],
            'compile_instruction' => 'Provide the running scene context-memory so the prose stays continuous.',
            'leak_rules' => ['none'],
            'order_index' => 6,
        ],
        [
            'key' => 'RESUME_ANCHOR',
            'agent' => BlockAgent::Narrator,
            'section' => BlockSection::User,
            'label' => '[RESUME ANCHOR]',
            'purpose' => 'Where to resume the narration from on session continuation.',
            'source_producers' => [['adr' => '0012', 'table' => 'play_sessions']],
            'compile_instruction' => "Anchor the continuation at the session's resume point.",
            'leak_rules' => ['none'],
            'order_index' => 7,
        ],
    ];

    public function run(): void
    {
        foreach (self::BLOCKS as $block) {
            PromptBlock::updateOrCreate(
                ['key' => $block['key']],
                [
                    'agent' => $block['agent'],
                    'section' => $block['section'],
                    'label' => $block['label'],
                    'purpose' => $block['purpose'],
                    'source_producers' => $block['source_producers'],
                    'compile_instruction' => $block['compile_instruction'],
                    'leak_rules' => $block['leak_rules'],
                    'order_index' => $block['order_index'],
                    'is_active' => true,
                ],
            );
        }
    }
}
