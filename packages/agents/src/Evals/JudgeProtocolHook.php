<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;
use Override;

/**
 * Enforces the judge's terminal-submission protocol. Registered for both
 * `BeforeToolUse` and `AfterStep`, branching on `HookContext::triggerType()`:
 *
 * - `BeforeToolUse`, no submission recorded yet: pass through unchanged.
 * - `BeforeToolUse`, a submission is already recorded, and the call is
 *   another `submit_judgment`: BLOCK it via `blockToolExecution()`. A second
 *   verdict is a genuine protocol violation and must fail the judge run.
 * - `BeforeToolUse`, a submission is already recorded, and the call is
 *   anything else: SKIP it - report a successful, marked-as-skipped
 *   execution instead of blocking, so a benign batch such as
 *   `[submit_judgment, policy_lookup]` does not fail the run just because
 *   `policy_lookup` happened to be called alongside the verdict. Blocking
 *   and skipping are different `HookContext` operations with different
 *   consequences for `AgentState::hasErrors()` - see
 *   `00-current-state-and-decisions.md`, "Use a terminal tool, not prose
 *   parsing", for the measured mechanism this relies on.
 * - `AfterStep`, a submission is recorded: add a `StopReason::Completed`
 *   stop signal so the loop ends immediately after the submission step,
 *   before any later step's tool calls ever reach `BeforeToolUse`.
 *
 * The terminal tool is identified by name only (`SubmitJudgmentTool::TOOL_NAME`),
 * never by its position within a batch of parallel tool calls.
 */
final readonly class JudgeProtocolHook implements HookInterface
{
    public function __construct(private JudgeSubmissionInbox $inbox) {}

    #[Override]
    public function handle(HookContext $context): HookContext {
        return match ($context->triggerType()) {
            HookTrigger::BeforeToolUse => $this->beforeToolUse($context),
            HookTrigger::AfterStep => $this->afterStep($context),
            default => $context,
        };
    }

    private function beforeToolUse(HookContext $context): HookContext {
        if (!$this->inbox->has()) {
            return $context;
        }

        $call = $context->toolCall();
        if ($call === null) {
            return $context;
        }

        if ($call->name() === SubmitJudgmentTool::TOOL_NAME) {
            return $context->blockToolExecution(
                'submit_judgment was already called for this judge run; only one judgment may be submitted.',
            );
        }

        $now = new DateTimeImmutable();
        return $context->with(
            isToolExecutionBlocked: true,
            toolExecution: new ToolExecution(
                toolCall: $call,
                result: Result::success([
                    'skipped' => true,
                    'reason' => 'submit_judgment already called; the judge run has ended.',
                ]),
                startedAt: $now,
                completedAt: $now,
            ),
        );
    }

    private function afterStep(HookContext $context): HookContext {
        if (!$this->inbox->has()) {
            return $context;
        }

        $state = $context->state();
        return $context->withState($state->withStopSignal(new StopSignal(
            reason: StopReason::Completed,
            message: 'Judge submitted its verdict via submit_judgment.',
            context: ['tool' => SubmitJudgmentTool::TOOL_NAME],
            source: self::class,
        )));
    }
}
