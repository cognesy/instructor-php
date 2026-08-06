<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use InvalidArgumentException;

final readonly class JudgeScore
{
    public function __construct(
        public float $score,
        public string $reason,
        public JudgeEvidence $evidence = new JudgeEvidence(),
        public ?AgentRun $run = null,
    ) {
        if (!is_finite($score) || $score < 0.0 || $score > 1.0) {
            throw new InvalidArgumentException('Judge score must be between 0 and 1.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Judge reason cannot be empty.');
        }
    }
}
