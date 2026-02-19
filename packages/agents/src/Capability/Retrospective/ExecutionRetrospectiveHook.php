<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrospective;

use Cognesy\Agents\Context\ContextSections;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Handles execution retrospective (rewind) by managing checkpoint markers
 * and truncating the message buffer when the agent calls the retrospective tool.
 *
 * BeforeStep: injects a visible CHECKPOINT N message so the LLM can reference it.
 * AfterStep: detects ExecutionRetrospectiveResult, truncates messages at the
 *            checkpoint boundary, and injects guidance from the agent's "future self".
 *
 * Execution state (steps, continuation) is NEVER modified — only the message
 * context changes. The agent loop, step limits, and token budgets continue normally.
 */
final class ExecutionRetrospectiveHook implements HookInterface
{
    public const string REWIND_COUNT_KEY = 'retrospective_rewind_count';
    public const string CHECKPOINT_COUNT_KEY = 'retrospective_checkpoint_count';

    public function __construct(
        private readonly RetrospectivePolicy $policy = new RetrospectivePolicy(),
        private readonly ?\Closure $onRewind = null,
    ) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        return match ($context->triggerType()) {
            HookTrigger::BeforeExecution => $this->handleBeforeExecution($context),
            HookTrigger::BeforeStep => $this->handleBeforeStep($context),
            HookTrigger::AfterStep => $this->handleAfterStep($context),
            default => $context,
        };
    }

    // BEFORE EXECUTION: append retrospective instructions to system prompt //

    private function handleBeforeExecution(HookContext $context): HookContext
    {
        $state = $context->state();

        // Only append retrospective instructions on the first execution
        if ($state->executionCount() > 1) {
            return $context;
        }

        $agentContext = $state->context();
        $agentContext = $agentContext->withSystemPrompt(
            $agentContext->systemPrompt() . "\n\n" . $this->policy->systemPromptInstructions
        );

        return $context->withState($state->with(context: $agentContext));
    }

    // BEFORE STEP: inject checkpoint marker ////////////////////////////////

    private function handleBeforeStep(HookContext $context): HookContext
    {
        $state = $context->state();
        $checkpointId = $state->metadata()->get(self::CHECKPOINT_COUNT_KEY, 0);

        $checkpointMessage = Message::asUser("[CHECKPOINT {$checkpointId}]")
            ->withMetadata('is_checkpoint', true)
            ->withMetadata('checkpoint_id', $checkpointId);

        $state = $state->withMessages(
            $state->messages()->appendMessage($checkpointMessage)
        );
        $state = $state->withMetadata(self::CHECKPOINT_COUNT_KEY, $checkpointId + 1);

        return $context->withState($state);
    }

    // AFTER STEP: detect retrospective result and rewind messages //////////

    private function handleAfterStep(HookContext $context): HookContext
    {
        $state = $context->state();
        $currentStep = $state->currentStep();

        if ($currentStep === null) {
            return $context;
        }

        $result = $this->findRetrospectiveResult($state);
        if ($result === null) {
            return $context;
        }

        $rewindCount = $state->metadata()->get(self::REWIND_COUNT_KEY, 0);
        if ($rewindCount >= $this->policy->maxRewinds) {
            return $context;
        }

        $newState = $this->rewindMessages($state, $result->checkpointId, $result->guidance, $rewindCount + 1);

        if ($this->onRewind !== null) {
            ($this->onRewind)($result, $newState);
        }

        return $context->withState($newState);
    }

    // INTERNAL /////////////////////////////////////////////////////////////

    private function findRetrospectiveResult(AgentState $state): ?ExecutionRetrospectiveResult
    {
        $step = $state->currentStep();
        if ($step === null) {
            return null;
        }

        $executions = $step->toolExecutions();
        if (!$executions->hasExecutions()) {
            return null;
        }

        foreach ($executions->all() as $execution) {
            if ($execution->hasError()) {
                continue;
            }
            $value = $execution->value();
            if ($value instanceof ExecutionRetrospectiveResult) {
                return $value;
            }
        }

        return null;
    }

    private function rewindMessages(AgentState $state, int $checkpointId, string $guidance, int $rewindCount): AgentState
    {
        // 1. Truncate messages to before the target checkpoint
        $truncatedMessages = $this->truncateAtCheckpoint($state->messages(), $checkpointId);

        // 2. Inject guidance as user message from "future self"
        $guidanceMessage = Message::asUser(
            "[EXECUTION RETROSPECTIVE]\n\n"
            . "Your future self has determined that the approach taken after checkpoint {$checkpointId} was suboptimal. "
            . "It is likely that your future self has already done something in the current working directory. "
            . "Please read the guidance and decide what to do next.\n\n"
            . $guidance
        );
        $truncatedMessages = $truncatedMessages->appendMessage($guidanceMessage);

        // 3. Clear stale BUFFER/SUMMARY sections
        $store = $state->store()
            ->removeSection(ContextSections::BUFFER)
            ->removeSection(ContextSections::SUMMARY);

        // 4. Apply to state — ONLY modify messages and metadata, NOT execution state
        $state = $state->withMessageStore($store);
        $state = $state->withMessages($truncatedMessages);
        $state = $state->withMetadata(self::REWIND_COUNT_KEY, $rewindCount);

        // 5. Reset checkpoint counter to the rewind point
        //    so the next BeforeStep creates CHECKPOINT {checkpointId} again
        $state = $state->withMetadata(self::CHECKPOINT_COUNT_KEY, $checkpointId);

        return $state;
    }

    private function truncateAtCheckpoint(Messages $messages, int $checkpointId): Messages
    {
        $kept = Messages::empty();
        foreach ($messages->each() as $msg) {
            $msgCheckpointId = $msg->metadata()->get('checkpoint_id');
            if ($msgCheckpointId !== null && $msgCheckpointId >= $checkpointId) {
                break;
            }
            $kept = $kept->appendMessage($msg);
        }
        return $kept;
    }
}
