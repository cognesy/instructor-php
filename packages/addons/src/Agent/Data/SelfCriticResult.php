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
        #[Description('False if: (1) evidence is insufficient for a confident answer, (2) response contradicts evidence, or (3) authoritative sources like composer.json were not checked. True ONLY if authoritative evidence supports the conclusion.')]
        public bool $approved,
        #[Description('One sentence: either "Approved: [reason]" or "Rejected: [specific contradiction or error found]"')]
        public string $summary,
        #[Description('Evidence that supports the conclusion (e.g., "Found pestphp/pest in composer.json require-dev")')]
        /** @var string[] */
        public array $strengths = [],
        #[Description('Specific contradictions or errors. Format: "[Response claims X] but [evidence shows Y]". Example: "Response says PHPUnit but composer.json showed pestphp/pest"')]
        /** @var string[] */
        public array $weaknesses = [],
        #[Description('Exact tool calls to resolve issues. Format: "[tool_name]: [what to check]". Example: "read_file: composer.json to check require-dev section"')]
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
