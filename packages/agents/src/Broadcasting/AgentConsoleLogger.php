<?php declare(strict_types=1);

namespace Cognesy\Agents\Broadcasting;

use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Events\AgentExecutionCompleted;
use Cognesy\Agents\Core\Events\AgentExecutionFailed;
use Cognesy\Agents\Core\Events\AgentExecutionStarted;
use Cognesy\Agents\Core\Events\AgentStepCompleted;
use Cognesy\Agents\Core\Events\AgentStepStarted;
use Cognesy\Agents\Core\Events\ContinuationEvaluated;
use Cognesy\Agents\Core\Events\DecisionExtractionFailed;
use Cognesy\Agents\Core\Events\HookExecuted;
use Cognesy\Agents\Core\Events\InferenceRequestStarted;
use Cognesy\Agents\Core\Events\InferenceResponseReceived;
use Cognesy\Agents\Core\Events\SubagentCompleted;
use Cognesy\Agents\Core\Events\SubagentSpawning;
use Cognesy\Agents\Core\Events\ToolCallBlocked;
use Cognesy\Agents\Core\Events\ToolCallCompleted;
use Cognesy\Agents\Core\Events\ToolCallStarted;
use Cognesy\Agents\Core\Events\ValidationFailed;
use Cognesy\Events\Event;
use DateTimeImmutable;

/**
 * Simple console logger for agent events.
 *
 * Provides developer-friendly output showing agent execution stages:
 * - Execution start/end
 * - Step progress
 * - Tool calls
 * - Continuation decisions
 * - Inference requests/responses
 * - Subagent spawning/completion
 * - Hook execution
 * - Validation/extraction failures
 *
 * Usage:
 *   $logger = new AgentConsoleLogger();
 *   $agent->wiretap($logger->wiretap());
 *   $agent->execute($state);
 */
final class AgentConsoleLogger
{
    private bool $useColors;
    private bool $showTimestamps;
    private bool $showAgentIds;
    private bool $showContinuation;
    private bool $showToolArgs;
    private bool $showInference;
    private bool $showSubagents;
    private bool $showHooks;
    private bool $showFailures;
    private int $maxArgLength;

    // Current context for events that don't include agent IDs
    private ?string $currentAgentId = null;
    private ?string $currentParentId = null;

    public function __construct(
        bool $useColors = true,
        bool $showTimestamps = true,
        bool $showAgentIds = true,
        bool $showContinuation = true,
        bool $showToolArgs = true,
        bool $showInference = true,
        bool $showSubagents = true,
        bool $showHooks = false,
        bool $showFailures = true,
        int $maxArgLength = 100,
    ) {
        $this->useColors = $useColors && $this->supportsColors();
        $this->showTimestamps = $showTimestamps;
        $this->showAgentIds = $showAgentIds;
        $this->showContinuation = $showContinuation;
        $this->showToolArgs = $showToolArgs;
        $this->showInference = $showInference;
        $this->showSubagents = $showSubagents;
        $this->showHooks = $showHooks;
        $this->showFailures = $showFailures;
        $this->maxArgLength = $maxArgLength;
    }

    /**
     * Returns a wiretap callable for use with Agent::wiretap()
     */
    public function wiretap(): callable
    {
        return function (Event $event): void {
            match (true) {
                // Core lifecycle events
                $event instanceof AgentExecutionStarted => $this->onExecutionStarted($event),
                $event instanceof AgentExecutionCompleted => $this->onExecutionCompleted($event),
                $event instanceof AgentExecutionFailed => $this->onExecutionFailed($event),
                $event instanceof AgentStepStarted => $this->onStepStarted($event),
                $event instanceof AgentStepCompleted => $this->onStepCompleted($event),
                $event instanceof ContinuationEvaluated => $this->onContinuationEvaluated($event),
                // Tool events
                $event instanceof ToolCallStarted => $this->onToolStarted($event),
                $event instanceof ToolCallCompleted => $this->onToolCompleted($event),
                $event instanceof ToolCallBlocked => $this->onToolBlocked($event),
                // Inference events
                $event instanceof InferenceRequestStarted => $this->onInferenceRequestStarted($event),
                $event instanceof InferenceResponseReceived => $this->onInferenceResponseReceived($event),
                // Subagent events
                $event instanceof SubagentSpawning => $this->onSubagentSpawning($event),
                $event instanceof SubagentCompleted => $this->onSubagentCompleted($event),
                // Hook events
                $event instanceof HookExecuted => $this->onHookExecuted($event),
                // Failure events
                $event instanceof DecisionExtractionFailed => $this->onDecisionExtractionFailed($event),
                $event instanceof ValidationFailed => $this->onValidationFailed($event),
                default => null,
            };
        };
    }

    private function onExecutionStarted(AgentExecutionStarted $event): void
    {
        // Track current context
        $this->currentAgentId = $event->agentId;
        $this->currentParentId = $event->parentAgentId;

        $this->logWithAgent('EXEC', 'cyan', sprintf(
            'Execution started [messages=%d, tools=%d]',
            $event->messageCount,
            $event->availableTools,
        ), $event->agentId, $event->parentAgentId);
    }

    private function onExecutionCompleted(AgentExecutionCompleted $event): void
    {
        $usage = $event->totalUsage;
        $this->logWithAgent('DONE', 'green', sprintf(
            'Execution completed [status=%s, steps=%d, tokens=%d]',
            $event->status->value,
            $event->totalSteps,
            $usage->total(),
        ), $event->agentId, $event->parentAgentId);
    }

    private function onExecutionFailed(AgentExecutionFailed $event): void
    {
        $this->logWithAgent('FAIL', 'red', sprintf(
            'Execution failed [status=%s, steps=%d, error=%s]',
            $event->status->value,
            $event->stepsCompleted,
            $this->truncate($event->exception->getMessage(), 100),
        ), $event->agentId, $event->parentAgentId);
    }

    private function onStepStarted(AgentStepStarted $event): void
    {
        // Track current context
        $this->currentAgentId = $event->agentId;
        $this->currentParentId = $event->parentAgentId;

        $this->logWithAgent('STEP', 'blue', sprintf(
            'Step %d started [messages=%d]',
            $event->stepNumber,
            $event->messageCount,
        ), $event->agentId, $event->parentAgentId);
    }

    private function onStepCompleted(AgentStepCompleted $event): void
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

        $usage = $event->usage;
        if ($usage->total() > 0) {
            $details[] = sprintf('tokens=%d', $usage->total());
        }

        if ($event->durationMs > 0) {
            $details[] = sprintf('duration=%dms', $event->durationMs);
        }

        $detailsStr = !empty($details) ? ' [' . implode(', ', $details) . ']' : '';

        $this->logWithAgent('STEP', 'blue', sprintf(
            'Step %d completed%s',
            $event->stepNumber,
            $detailsStr,
        ), $event->agentId, $event->parentAgentId);
    }

    private function onToolStarted(ToolCallStarted $event): void
    {
        $argsStr = '';
        if ($this->showToolArgs && is_array($event->args) && !empty($event->args)) {
            $argsStr = ' ' . $this->formatArgs($event->args);
        }

        $this->log('TOOL', 'yellow', sprintf(
            'Calling %s%s',
            $event->tool,
            $argsStr,
        ));
    }

    private function onToolCompleted(ToolCallCompleted $event): void
    {
        $duration = $this->durationMs($event->startedAt, $event->completedAt);

        if ($event->success) {
            $this->log('TOOL', 'yellow', sprintf(
                'Tool %s completed [duration=%dms]',
                $event->tool,
                $duration,
            ));
        } else {
            $this->log('TOOL', 'red', sprintf(
                'Tool %s failed [error=%s, duration=%dms]',
                $event->tool,
                $this->truncate($event->error ?? 'unknown', 80),
                $duration,
            ));
        }
    }

    private function onContinuationEvaluated(ContinuationEvaluated $event): void
    {
        if (!$this->showContinuation) {
            return;
        }

        $outcome = $event->outcome;
        $decision = $outcome->shouldContinue ? 'CONTINUE' : 'STOP';
        $color = $outcome->shouldContinue ? 'magenta' : 'cyan';

        $details = [
            sprintf('decision=%s', $decision),
            sprintf('reason=%s', $outcome->stopReason()->value),
            sprintf('resolved_by=%s', $outcome->resolvedBy()),
        ];

        $this->logWithAgent('EVAL', $color, sprintf(
            'Continuation evaluated [%s]',
            implode(', ', $details),
        ), $event->agentId, $event->parentAgentId);

        // Show individual evaluations for debugging
        foreach ($outcome->evaluations as $eval) {
            /** @var ContinuationEvaluation $eval */
            $criterionName = basename(str_replace('\\', '/', $eval->criterionClass));
            $this->logWithAgent('    ', 'dark', sprintf(
                '%s: %s - %s',
                $criterionName,
                $eval->decision->value,
                $eval->reason,
            ), $event->agentId, $event->parentAgentId);
        }
    }

    private function onToolBlocked(ToolCallBlocked $event): void
    {
        $hookInfo = $event->hookName ? " by '{$event->hookName}'" : '';
        $this->log('BLCK', 'red', sprintf(
            'Tool %s blocked%s: %s',
            $event->tool,
            $hookInfo,
            $this->truncate($event->reason, 100),
        ));
    }

    private function onInferenceRequestStarted(InferenceRequestStarted $event): void
    {
        if (!$this->showInference) {
            return;
        }

        $modelInfo = $event->model ? sprintf(' model=%s', $event->model) : '';
        $this->logWithAgent('LLM ', 'cyan', sprintf(
            'Inference request [messages=%d%s]',
            $event->messageCount,
            $modelInfo,
        ), $event->agentId, $event->parentAgentId);
    }

    private function onInferenceResponseReceived(InferenceResponseReceived $event): void
    {
        if (!$this->showInference) {
            return;
        }

        $tokens = $event->usage?->total() ?? 0;
        $finishInfo = $event->finishReason ? sprintf(', finish=%s', $event->finishReason) : '';

        $this->logWithAgent('LLM ', 'cyan', sprintf(
            'Inference response [tokens=%d, duration=%dms%s]',
            $tokens,
            $this->durationMs($event->requestStartedAt, $event->receivedAt),
            $finishInfo,
        ), $event->agentId, $event->parentAgentId);
    }

    private function onSubagentSpawning(SubagentSpawning $event): void
    {
        if (!$this->showSubagents) {
            return;
        }

        // Use parent's context - subagent doesn't have ID yet
        $this->logWithAgent('SUB ', 'magenta', sprintf(
            'Spawning subagent "%s" [depth=%d/%d, prompt=%s]',
            $event->subagentName,
            $event->depth + 1,
            $event->maxDepth,
            $this->truncate($event->prompt, 60),
        ), $this->currentAgentId, $this->currentParentId);
    }

    private function onSubagentCompleted(SubagentCompleted $event): void
    {
        if (!$this->showSubagents) {
            return;
        }

        $tokens = $event->usage?->total() ?? 0;
        $duration = $this->durationMs($event->startedAt, $event->completedAt);

        // Show from parent's perspective with subagent ID
        $this->logWithAgent('SUB ', 'magenta', sprintf(
            'Subagent "%s" [%s] completed [status=%s, steps=%d, tokens=%d, duration=%dms]',
            $event->subagentName,
            $this->shortId($event->subagentId, 4),
            $event->status->value,
            $event->steps,
            $tokens,
            $duration,
        ), $this->currentAgentId, $this->currentParentId);
    }

    private function onHookExecuted(HookExecuted $event): void
    {
        if (!$this->showHooks) {
            return;
        }

        $reasonInfo = $event->reason ? sprintf(' (%s)', $this->truncate($event->reason, 50)) : '';
        $duration = $this->durationMs($event->startedAt, $event->completedAt);

        $color = match ($event->outcome) {
            'blocked', 'stopped' => 'red',
            default => 'dark',
        };

        $this->log('HOOK', $color, sprintf(
            '%s hook for "%s": %s%s [%dms]',
            $event->hookType,
            $event->tool,
            $event->outcome,
            $reasonInfo,
            $duration,
        ));
    }

    private function onDecisionExtractionFailed(DecisionExtractionFailed $event): void
    {
        if (!$this->showFailures) {
            return;
        }

        $attemptInfo = $event->maxAttempts > 1
            ? sprintf(' (attempt %d/%d)', $event->attemptNumber, $event->maxAttempts)
            : '';

        $this->logWithAgent('FAIL', 'red', sprintf(
            'Decision extraction failed%s: %s',
            $attemptInfo,
            $this->truncate($event->errorMessage, 100),
        ), $event->agentId, $event->parentAgentId);
    }

    private function onValidationFailed(ValidationFailed $event): void
    {
        if (!$this->showFailures) {
            return;
        }

        $errorSummary = implode('; ', array_slice($event->errors, 0, 3));
        $moreInfo = count($event->errors) > 3 ? sprintf(' (+%d more)', count($event->errors) - 3) : '';

        $this->logWithAgent('FAIL', 'red', sprintf(
            'Validation failed (%s): %s%s',
            $event->validationType,
            $this->truncate($errorSummary, 80),
            $moreInfo,
        ), $event->agentId, $event->parentAgentId);
    }

    private function log(string $label, string $color, string $message): void
    {
        $this->logWithAgent($label, $color, $message, $this->currentAgentId, $this->currentParentId);
    }

    private function logWithAgent(string $label, string $color, string $message, ?string $agentId, ?string $parentAgentId): void
    {
        $timestamp = '';
        if ($this->showTimestamps) {
            $timestamp = (new DateTimeImmutable())->format('H:i:s.v') . ' ';
        }

        $agentInfo = '';
        if ($this->showAgentIds && $agentId !== null) {
            $parentPart = $parentAgentId !== null ? $this->shortId($parentAgentId, 4) : '----';
            $agentPart = $this->shortId($agentId, 4);
            $agentInfo = sprintf('[%s:%s] ', $parentPart, $agentPart);
        }

        $labelFormatted = $this->colorize(str_pad($label, 4), $color);

        echo sprintf("%s%s[%s] %s\n", $timestamp, $agentInfo, $labelFormatted, $message);
    }

    private function colorize(string $text, string $color): string
    {
        if (!$this->useColors) {
            return $text;
        }

        $codes = [
            'red' => '31',
            'green' => '32',
            'yellow' => '33',
            'blue' => '34',
            'magenta' => '35',
            'cyan' => '36',
            'dark' => '90',
        ];

        $code = $codes[$color] ?? '0';
        return "\033[{$code}m{$text}\033[0m";
    }

    private function supportsColors(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || str_starts_with((string) getenv('TERM'), 'xterm');
        }

        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
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
        return substr($text, 0, $maxLength - 3) . '...';
    }

    private function formatArgs(array $args): string
    {
        $parts = [];
        foreach (array_slice($args, 0, 5, true) as $key => $value) {
            $valueStr = match (true) {
                is_string($value) => $this->truncate($value, $this->maxArgLength),
                is_bool($value) => $value ? 'true' : 'false',
                is_null($value) => 'null',
                is_array($value) => '[array]',
                is_object($value) => '[object]',
                default => (string) $value,
            };
            $parts[] = "{$key}={$valueStr}";
        }

        $more = count($args) > 5 ? sprintf(' (+%d more)', count($args) - 5) : '';
        return '{' . implode(', ', $parts) . $more . '}';
    }

    private function durationMs(DateTimeImmutable $startedAt, DateTimeImmutable $completedAt): int
    {
        $diff = $completedAt->getTimestamp() - $startedAt->getTimestamp();
        $microDiff = (int) ($completedAt->format('u')) - (int) ($startedAt->format('u'));
        return ($diff * 1000) + (int) ($microDiff / 1000);
    }
}
