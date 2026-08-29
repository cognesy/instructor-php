<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Utils\Result\Result;
use Override;

/** Applies Tell-owned wall, output, and tool-call limits at the Agents hook boundary. */
final class TellExecutionBudgetHook implements HookInterface
{
    private ?int $startedAtMs = null;
    private int $toolCalls = 0;
    private int $modelOutputBytes = 0;
    private bool $toolLimitExceeded = false;

    public function __construct(
        private readonly TellExecutionPolicy $policy,
        private readonly CanReadTellClock $clock,
    ) {}

    #[Override]
    public function handle(HookContext $context): HookContext
    {
        return match ($context->triggerType()) {
            HookTrigger::BeforeExecution => $this->start($context),
            HookTrigger::BeforeStep, HookTrigger::BeforeInferenceRequest => $this->enforceTime($context),
            HookTrigger::BeforeToolUse => $this->reserveToolCall($context),
            HookTrigger::AfterToolUse => $this->truncateToolOutput($context),
            HookTrigger::AfterStep => $this->enforceAfterStep($context),
            default => $context,
        };
    }

    private function start(HookContext $context): HookContext
    {
        $this->startedAtMs = $this->clock->nowMs();
        return $context;
    }

    private function enforceTime(HookContext $context): HookContext
    {
        if ($this->startedAtMs === null) {
            return $context;
        }
        $elapsed = $this->clock->nowMs() - $this->startedAtMs;
        if ($elapsed < $this->policy->timeoutMs) {
            return $context;
        }

        return $this->stop($context, StopReason::TimeLimitReached, 'Tell wall-time budget exhausted.', [
            'elapsedMs' => $elapsed,
            'limitMs' => $this->policy->timeoutMs,
        ]);
    }

    private function reserveToolCall(HookContext $context): HookContext
    {
        if ($this->toolCalls < $this->policy->maxToolCalls) {
            ++$this->toolCalls;
            return $context;
        }
        $this->toolLimitExceeded = true;

        return $context->blockToolExecution('Tell tool-call budget exhausted.');
    }

    private function truncateToolOutput(HookContext $context): HookContext
    {
        // A spilled result is already a short stub, and cutting it further
        // would strip the path and read hint that make it useful.
        if ($context->metadata(TellSpillToolOutputHook::SPILLED) === true) {
            return $context;
        }
        $execution = $context->toolExecution();
        if ($execution === null || ! $execution->result()->isSuccess()) {
            return $context;
        }
        $encoded = json_encode($execution->value(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($encoded) || strlen($encoded) <= $this->policy->maxToolOutputChars) {
            return $context;
        }
        $value = $execution->value();
        $replacement = is_string($value)
            ? self::truncateUtf8($value, $this->policy->maxToolOutputChars)
            : ['truncated' => true, 'originalBytes' => strlen($encoded)];

        return $context->withToolExecution(new ToolExecution(
            toolCall: $execution->toolCall(),
            result: Result::success($replacement),
            startedAt: $execution->startedAt(),
            completedAt: $execution->completedAt(),
            id: $execution->id(),
        ));
    }

    private function enforceAfterStep(HookContext $context): HookContext
    {
        if ($this->toolLimitExceeded) {
            return $this->stop($context, $this->limitReason('tool_call_limit'), 'Tell tool-call budget exhausted.', [
                'toolCalls' => $this->toolCalls,
                'limit' => $this->policy->maxToolCalls,
            ]);
        }
        $response = $context->state()->currentResponse()->toString();
        $this->modelOutputBytes += strlen($response);
        if ($this->modelOutputBytes <= $this->policy->maxOutputChars) {
            return $context;
        }

        return $this->stop($context, $this->limitReason('output_limit'), 'Tell model-output budget exhausted.', [
            'outputBytes' => $this->modelOutputBytes,
            'limit' => $this->policy->maxOutputChars,
        ]);
    }

    /** @param array<string, int> $details */
    private function stop(HookContext $context, StopReason $reason, string $message, array $details): HookContext
    {
        return $context->withState($context->state()->withStopSignal(new StopSignal(
            reason: $reason,
            message: $message,
            context: $details,
            source: self::class,
        )));
    }

    /**
     * Tell can run with the earliest compatible Agents 2.8 package, which did
     * not yet expose Tell's two dedicated limit cases. Current Agents retains
     * the precise value; old installations degrade to its established token
     * limit rather than failing during class loading.
     */
    private function limitReason(string $value): StopReason
    {
        return StopReason::tryFrom($value) ?? StopReason::TokenLimitReached;
    }

    private static function truncateUtf8(string $value, int $limit): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }
        $suffix = '… [truncated]';
        if ($limit <= strlen($suffix)) {
            $suffix = '';
        }
        $bytes = substr($value, 0, max(0, $limit - strlen($suffix)));
        while ($bytes !== '' && preg_match('//u', $bytes) !== 1) {
            $bytes = substr($bytes, 0, -1);
        }

        return $bytes.$suffix;
    }
}
