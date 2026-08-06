<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

/**
 * Mutable mailbox shared between `SubmitJudgmentTool` and `JudgeProtocolHook`
 * for a single `AgentLoopJudge::judge()` call. Holds at most one accepted
 * submission - `SubmitJudgmentTool` only ever calls `submit()` once, because
 * `JudgeProtocolHook` blocks every `submit_judgment` call after the first one
 * reaches this inbox, so `submit()` never overwrites an existing submission.
 *
 * `attempts()` counts every call to `submit()`, accepted or not, which is
 * strictly the number of times the tool body itself ran (a blocked call never
 * reaches the tool body at all, so a blocked second `submit_judgment` does
 * NOT bump this counter - the protocol violation is visible through the
 * judge run's error state instead, not through this count).
 */
final class JudgeSubmissionInbox
{
    private ?JudgeSubmission $submission = null;
    private int $attempts = 0;

    public function submit(JudgeSubmission $submission): void {
        $this->attempts++;
        if ($this->submission === null) {
            $this->submission = $submission;
        }
    }

    public function has(): bool {
        return $this->submission !== null;
    }

    public function get(): ?JudgeSubmission {
        return $this->submission;
    }

    public function attempts(): int {
        return $this->attempts;
    }
}
