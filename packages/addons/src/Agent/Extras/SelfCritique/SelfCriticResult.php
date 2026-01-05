<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Extras\SelfCritique;

/**
 * Structured result from self-critic evaluation.
 * Used with Instructor to get deterministic, parseable responses.
 */
class SelfCriticResult
{
    public function __construct(
        /** True if response answers the task with supporting evidence. False if response contradicts evidence or makes unsupported claims. */
        public bool $approved,
        /** One sentence explaining the decision. */
        public string $summary,
        /** @var string[] List of positive aspects as simple strings. */
        public array $strengths = [],
        /** @var string[] List of issues as simple strings. */
        public array $weaknesses = [],
        /** @var string[] List of suggested next steps as simple strings. */
        public array $suggestions = [],
    ) {}

    public function toFeedback(): string {
        $feedback = [];

        if (!empty($this->weaknesses)) {
            $feedback[] = "**Issues to address:**";
            foreach ($this->weaknesses as $weakness) {
                $feedback[] = "- {$weakness}";
            }
        }

        if (!empty($this->suggestions)) {
            $feedback[] = "\n**Suggestions:**";
            foreach ($this->suggestions as $suggestion) {
                $feedback[] = "- {$suggestion}";
            }
        }

        return implode("\n", $feedback);
    }
}
