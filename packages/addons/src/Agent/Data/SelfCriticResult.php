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
        #[Description('True if response is supported by evidence and answers the task. False if response contradicts evidence, makes unsupported claims, or missed critical information.')]
        public bool $approved,
        #[Description('One sentence summary of the evaluation decision.')]
        public string $summary,
        #[Description('What the response does well - evidence cited, clear reasoning, etc.')]
        /** @var string[] */
        public array $strengths = [],
        #[Description('Specific issues found. Format: "Response claims X but evidence shows Y" or "Response missed X in the tool results"')]
        /** @var string[] */
        public array $weaknesses = [],
        #[Description('Specific next steps to fix issues. Format: "tool_name: what to check" or "file to read"')]
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
