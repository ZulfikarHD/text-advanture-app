<?php

namespace App\Services\Narrator;

use App\Enums\BlockSection;
use App\Services\Llm\Data\LlmRequest;
use Illuminate\Support\Collection;

/**
 * The assembled narrator prompt — the registry blocks lit for one turn, in order.
 *
 * The output of {@see NarratorPromptAssembler}: an ordered list of folded
 * {@see AssembledBlock}s plus the trailing user instruction. Holds no LLM call;
 * {@see NarratorTurnService} (S-4.2.1) turns {@see self::messages()} into an
 * {@see LlmRequest} and sends it. Blocks whose producers
 * are not yet built are simply absent — this object never carries filler.
 */
final readonly class AssembledPrompt
{
    /**
     * @param  list<AssembledBlock>  $blocks  The lit blocks, ordered by section then order_index.
     * @param  string  $userInstruction  The trailing directive appended to the user message.
     */
    public function __construct(
        private array $blocks,
        private string $userInstruction,
    ) {}

    /**
     * The lit blocks in assembled order.
     *
     * @return list<AssembledBlock>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }

    /**
     * The keys of the lit blocks, in assembled order.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(fn (AssembledBlock $block): string => $block->key, $this->blocks);
    }

    /**
     * The lit block with the given key, or null when it was not lit this turn.
     *
     * @param  string  $key  The registry key (e.g. `POV_CONTRACT`).
     */
    public function block(string $key): ?AssembledBlock
    {
        foreach ($this->blocks as $block) {
            if ($block->key === $key) {
                return $block;
            }
        }

        return null;
    }

    /**
     * Render the prompt as chat messages for the LLM transport.
     *
     * System-section blocks fold into one `system` message; user-section blocks
     * (e.g. RESUME_ANCHOR, when resuming) fold into the `user` message, which
     * always ends with the continue instruction. An empty section is omitted —
     * the user message is always present so the narrator has its directive.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function messages(): array
    {
        $messages = [];

        $system = $this->renderSection(BlockSection::System);

        if ($system !== '') {
            $messages[] = ['role' => BlockSection::System->value, 'content' => $system];
        }

        $user = $this->renderSection(BlockSection::User);
        $content = $user === ''
            ? $this->userInstruction
            : $user."\n\n".$this->userInstruction;

        $messages[] = ['role' => BlockSection::User->value, 'content' => $content];

        return $messages;
    }

    /**
     * Fold every block in a section into one labelled string.
     */
    private function renderSection(BlockSection $section): string
    {
        return (new Collection($this->blocks))
            ->filter(fn (AssembledBlock $block): bool => $block->section === $section)
            ->map(fn (AssembledBlock $block): string => $block->label."\n".$block->body)
            ->implode("\n\n");
    }
}
