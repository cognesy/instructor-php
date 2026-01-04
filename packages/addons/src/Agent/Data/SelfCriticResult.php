<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Data;

use Cognesy\Instructor\Attributes\Description;

/**
 * Structured result from self-critic evaluation.
 * Used with Instructor to get deterministic, parseable responses.
 */
class SelfCriticResult
{
    public function __construct(
        #[Description('True if response answers the task with supporting evidence. False if response contradicts evidence or makes unsupported claims.')]
        public bool $approved,
        #[Description('One sentence explaining the decision.')]
        public string $summary,
        #[Description('List of positive aspects as simple strings.')]
        /** @var string[] */
        public array $strengths = [],
        #[Description('List of issues as simple strings.')]
        /** @var string[] */
        public array $weaknesses = [],
        #[Description('List of suggested next steps as simple strings.')]
        /** @var string[] */
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
