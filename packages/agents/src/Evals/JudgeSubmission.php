<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

/**
 * A single validated `submit_judgment` call, captured by a
 * `JudgeSubmissionInbox`. Score and reason mirror `JudgeScore`'s own
 * invariants; `SubmitJudgmentTool` validates the raw tool arguments before
 * ever constructing one of these.
 */
final readonly class JudgeSubmission
{
    public function __construct(
        public float $score,
        public string $reason,
        public JudgeEvidence $evidence = new JudgeEvidence(),
    ) {}
}
