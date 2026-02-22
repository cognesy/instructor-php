<?php declare(strict_types=1);

namespace Cognesy\Agents\Events\Support;

use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\ContinuationEvaluated;
use Cognesy\Agents\Events\DecisionExtractionFailed;
use Cognesy\Agents\Events\HookExecuted;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Agents\Events\InferenceResponseReceived;
use Cognesy\Agents\Events\SubagentCompleted;
use Cognesy\Agents\Events\SubagentSpawning;
use Cognesy\Agents\Events\ToolCallBlocked;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
use Cognesy\Agents\Events\ValidationFailed;
use Cognesy\Events\Contracts\CanFormatConsoleEvent;
use Cognesy\Events\Data\ConsoleEventLine;
use Cognesy\Events\Enums\ConsoleColor;
use DateTimeImmutable;

final class AgentEventConsoleFormatter implements CanFormatConsoleEvent
{
    private ?string $currentAgentId = null;
    private ?string $currentParentId = null;

    public function __construct(
        private readonly bool $showAgentIds = true,
        private readonly bool $showContinuation = true,
        private readonly bool $showToolArgs = true,
        private readonly bool $showInference = true,
        private readonly bool $showSubagents = true,
        private readonly bool $showHooks = false,
        private readonly bool $showFailures = true,
        private readonly int $maxArgLength = 100,
    ) {}

    public function format(object $event): ?ConsoleEventLine {
        return match (true) {
            $event instanceof AgentExecutionStarted => $this->onExecutionStarted($event),
            $event instanceof AgentExecutionCompleted => $this->onExecutionCompleted($event),
            $event instanceof AgentExecutionFailed => $this->onExecutionFailed($event),
            $event instanceof AgentStepStarted => $this->onStepStarted($event),
            $event instanceof AgentStepCompleted => $this->onStepCompleted($event),
            $event instanceof ContinuationEvaluated => $this->onContinuationEvaluated($event),
            $event instanceof ToolCallStarted => $this->onToolStarted($event),
            $event instanceof ToolCallCompleted => $this->onToolCompleted($event),
            $event instanceof ToolCallBlocked => $this->onToolBlocked($event),
            $event instanceof InferenceRequestStarted => $this->onInferenceRequestStarted($event),
            $event instanceof InferenceResponseReceived => $this->onInferenceResponseReceived($event),
            $event instanceof SubagentSpawning => $this->onSubagentSpawning($event),
            $event instanceof SubagentCompleted => $this->onSubagentCompleted($event),
            $event instanceof HookExecuted => $this->onHookExecuted($event),
            $event instanceof DecisionExtractionFailed => $this->onDecisionExtractionFailed($event),
            $event instanceof ValidationFailed => $this->onValidationFailed($event),
            default => null,
        };
    }

    private function onExecutionStarted(AgentExecutionStarted $event): ConsoleEventLine
    {
        $this->currentAgentId = $event->agentId;
        $this->currentParentId = $event->parentAgentId;

        return $this->lineWithAgent(
            'EXEC',
            ConsoleColor::Cyan,
            sprintf('Execution started [messages=%d, tools=%d]', $event->messageCount, $event->availableTools),
            $event->agentId,
            $event->parentAgentId,
        );
    }

    private function onExecutionCompleted(AgentExecutionCompleted $event): ConsoleEventLine
    {
        return $this->lineWithAgent(
            'DONE',
            ConsoleColor::Green,
            sprintf('Execution completed [status=%s, steps=%d, tokens=%d]', $event->status->value, $event->totalSteps, $event->totalUsage->total()),
            $event->agentId,
            $event->parentAgentId,
        );
    }

    private function onExecutionFailed(AgentExecutionFailed $event): ConsoleEventLine
    {
        return $this->lineWithAgent(
            'FAIL',
            ConsoleColor::Red,
            sprintf('Execution failed [status=%s, steps=%d, error=%s]', $event->status->value, $event->stepsCompleted, $this->truncate($event->exception->getMessage(), 100)),
            $event->agentId,
            $event->parentAgentId,
        );
    }

    private function onStepStarted(AgentStepStarted $event): ConsoleEventLine
    {
        $this->currentAgentId = $event->agentId;
        $this->currentParentId = $event->parentAgentId;

        return $this->lineWithAgent(
            'STEP',
            ConsoleColor::Blue,
            sprintf('Step %d started [messages=%d]', $event->stepNumber, $event->messageCount),
            $event->agentId,
            $event->parentAgentId,
        );
    }

    private function onStepCompleted(AgentStepCompleted $event): ConsoleEventLine
    {
        $details = [];

        if ($event->hasToolCalls) {
            $details[] = 'has_tool_calls';
        }

        if ($event->errorCount > 0) {
            $details[] = sprintf('errors=%d', $event->errorCount);
        }

        if ($event->finishReason !== null) {
            $details[] = sprintf('finish=%s', $event->finishReason->value);
        }

        if ($event->usage->total() > 0) {
            $details[] = sprintf('tokens=%d', $event->usage->total());
        }

        if ($event->durationMs > 0) {
            $details[] = sprintf('duration=%dms', (int) $event->durationMs);
        }

        $suffix = $details !== [] ? ' ['.implode(', ', $details).']' : '';

        return $this->lineWithAgent(
            'STEP',
            ConsoleColor::Blue,
            sprintf('Step %d completed%s', $event->stepNumber, $suffix),
            $event->agentId,
            $event->parentAgentId,
        );
    }

    private function onToolStarted(ToolCallStarted $event): ConsoleEventLine
    {
        $args = '';
        if ($this->showToolArgs && is_array($event->args) && $event->args !== []) {
            $args = ' '.$this->formatArgs($event->args);
        }

        return $this->line('TOOL', ConsoleColor::Yellow, sprintf('Calling %s%s', $event->tool, $args));
    }

    private function onToolCompleted(ToolCallCompleted $event): ConsoleEventLine
    {
        $duration = $this->durationMs($event->startedAt, $event->completedAt);

        if ($event->success) {
            return $this->line('TOOL', ConsoleColor::Yellow, sprintf('Tool %s completed [duration=%dms]', $event->tool, $duration));
        }

        return $this->line(
            'TOOL',
            ConsoleColor::Red,
            sprintf('Tool %s failed [error=%s, duration=%dms]', $event->tool, $this->truncate($event->error ?? 'unknown', 80), $duration),
        );
    }

    private function onContinuationEvaluated(ContinuationEvaluated $event): ?ConsoleEventLine
    {
        if (!$this->showContinuation) {
            return null;
        }

        $decision = $event->shouldStop() ? 'STOP' : 'CONTINUE';
        $color = $event->shouldStop() ? ConsoleColor::Cyan : ConsoleColor::Magenta;
        $reason = $event->stopReason()?->value ?? 'none';
        $details = [sprintf('decision=%s', $decision), sprintf('reason=%s', $reason)];
        $resolvedBy = $event->resolvedBy();

        if ($resolvedBy !== '') {
            $details[] = sprintf('resolved_by=%s', $resolvedBy);
        }

        return $this->lineWithAgent(
            'EVAL',
            $color,
            sprintf('Continuation evaluated [%s]', implode(', ', $details)),
            $event->agentId,
            $event->parentAgentId,
        );
    }

    private function onToolBlocked(ToolCallBlocked $event): ConsoleEventLine
    {
        $hook = $event->hookName ? " by '{$event->hookName}'" : '';
        return $this->line('BLCK', ConsoleColor::Red, sprintf('Tool %s blocked%s: %s', $event->tool, $hook, $this->truncate($event->reason, 100)));
    }

    private function onInferenceRequestStarted(InferenceRequestStarted $event): ?ConsoleEventLine
    {
        if (!$this->showInference) {
            return null;
        }

        $model = $event->model ? sprintf(' model=%s', $event->model) : '';

        return $this->lineWithAgent(
            'LLM ',
            ConsoleColor::Cyan,
            sprintf('Inference request [messages=%d%s]', $event->messageCount, $model),
            $event->agentId,
            $event->parentAgentId,
        );
    }

    private function onInferenceResponseReceived(InferenceResponseReceived $event): ?ConsoleEventLine
    {
        if (!$this->showInference) {
            return null;
        }

        $tokens = $event->usage?->total() ?? 0;
        $finish = $event->finishReason ? sprintf(', finish=%s', $event->finishReason) : '';

        return $this->lineWithAgent(
            'LLM ',
            ConsoleColor::Cyan,
            sprintf('Inference response [tokens=%d, duration=%dms%s]', $tokens, $this->durationMs($event->requestStartedAt, $event->receivedAt), $finish),
            $event->agentId,
            $event->parentAgentId,
        );
    }

    private function onSubagentSpawning(SubagentSpawning $event): ?ConsoleEventLine
    {
        if (!$this->showSubagents) {
            return null;
        }

        return $this->lineWithAgent(
            'SUB ',
            ConsoleColor::Magenta,
            sprintf(
                'Spawning subagent "%s" [depth=%d/%d, prompt=%s]',
                $event->subagentName,
                $event->depth + 1,
                $event->maxDepth,
                $this->truncate($event->prompt, 60),
            ),
            $this->currentAgentId,
            $this->currentParentId,
        );
    }

    private function onSubagentCompleted(SubagentCompleted $event): ?ConsoleEventLine
    {
        if (!$this->showSubagents) {
            return null;
        }

        return $this->lineWithAgent(
            'SUB ',
            ConsoleColor::Magenta,
            sprintf(
                'Subagent "%s" [%s] completed [status=%s, steps=%d, tokens=%d, duration=%dms]',
                $event->subagentName,
                $this->shortId($event->subagentId, 4),
                $event->status->value,
                $event->steps,
                $event->usage?->total() ?? 0,
                $this->durationMs($event->startedAt, $event->completedAt),
            ),
            $this->currentAgentId,
            $this->currentParentId,
        );
    }

    private function onHookExecuted(HookExecuted $event): ?ConsoleEventLine
    {
        if (!$this->showHooks) {
            return null;
        }

        return $this->line(
            'HOOK',
            ConsoleColor::Dark,
            sprintf('%s "%s" [%dms]', $event->triggerType, $event->hookName ?? 'anonymous', $this->durationMs($event->startedAt, $event->completedAt)),
        );
    }

    private function onDecisionExtractionFailed(DecisionExtractionFailed $event): ?ConsoleEventLine
    {
        if (!$this->showFailures) {
            return null;
        }

        $attempt = $event->maxAttempts > 1
            ? sprintf(' (attempt %d/%d)', $event->attemptNumber, $event->maxAttempts)
            : '';

        return $this->lineWithAgent(
            'FAIL',
            ConsoleColor::Red,
            sprintf('Decision extraction failed%s: %s', $attempt, $this->truncate($event->errorMessage, 100)),
            $event->agentId,
            $event->parentAgentId,
        );
    }

    private function onValidationFailed(ValidationFailed $event): ?ConsoleEventLine
    {
        if (!$this->showFailures) {
            return null;
        }

        $summary = implode('; ', array_slice($event->errors, 0, 3));
        $more = count($event->errors) > 3 ? sprintf(' (+%d more)', count($event->errors) - 3) : '';

        return $this->lineWithAgent(
            'FAIL',
            ConsoleColor::Red,
            sprintf('Validation failed (%s): %s%s', $event->validationType, $this->truncate($summary, 80), $more),
            $event->agentId,
            $event->parentAgentId,
        );
    }

    private function line(string $label, ConsoleColor $color, string $message): ConsoleEventLine
    {
        return $this->lineWithAgent($label, $color, $message, $this->currentAgentId, $this->currentParentId);
    }

    private function lineWithAgent(
        string $label,
        ConsoleColor $color,
        string $message,
        ?string $agentId,
        ?string $parentAgentId,
    ): ConsoleEventLine {
        return new ConsoleEventLine(
            label: $label,
            message: $message,
            color: $color,
            context: $this->agentContext($agentId, $parentAgentId),
        );
    }

    private function agentContext(?string $agentId, ?string $parentAgentId): ?string
    {
        if (!$this->showAgentIds || $agentId === null) {
            return null;
        }

        $parent = $parentAgentId !== null ? $this->shortId($parentAgentId, 4) : '----';
        $agent = $this->shortId($agentId, 4);

        return sprintf('[%s:%s]', $parent, $agent);
    }

    private function shortId(string $id, int $length = 8): string
    {
        return substr($id, -$length);
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength - 3).'...';
    }

    private function durationMs(DateTimeImmutable $startedAt, DateTimeImmutable $completedAt): int
    {
        $diff = $completedAt->getTimestamp() - $startedAt->getTimestamp();
        $microDiff = (int) $completedAt->format('u') - (int) $startedAt->format('u');

        return ($diff * 1000) + (int) ($microDiff / 1000);
    }

    private function formatArgs(array $args): string
    {
        $parts = [];

        foreach (array_slice($args, 0, 5, true) as $key => $value) {
            $parts[] = sprintf('%s=%s', $key, $this->stringifyArgValue($value));
        }

        $more = count($args) > 5 ? sprintf(' (+%d more)', count($args) - 5) : '';

        return '{'.implode(', ', $parts).$more.'}';
    }

    private function stringifyArgValue(mixed $value): string
    {
        return match (true) {
            is_string($value) => $this->truncate($value, $this->maxArgLength),
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            is_array($value) => '[array]',
            is_object($value) => '[object]',
            default => (string) $value,
        };
    }
}
