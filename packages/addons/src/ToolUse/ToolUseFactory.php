<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPresenceCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\RetryLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ToolCallPresenceCheck;
use Cognesy\Addons\StepByStep\StateProcessing\CanApplyProcessors;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendContextMetadata;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;

class ToolUseFactory
{
    public static function default(
        ?Tools $tools = null,
        ?CanApplyProcessors $processors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanUseTools $driver = null,
        ?CanHandleEvents $events = null,
    ): ToolUse {
        $events = EventBusResolver::using($events);
        $tools = $tools ?? new Tools();

        return new ToolUse(
            tools: $tools,
            processors: $processors ?? self::defaultProcessors(),
            continuationCriteria: $continuationCriteria ?? self::defaultContinuationCriteria(),
            driver: $driver ?? new ToolCallingDriver,
            events: $events,
        );
    }

    protected static function defaultProcessors(): CanApplyProcessors {
        return new StateProcessors(
            new AccumulateTokenUsage(),
            new AppendContextMetadata(),
            new AppendStepMessages(),
        );
    }

    protected static function defaultContinuationCriteria(
        int $maxSteps = 3,
        int $maxTokens = 8192,
        int $maxExecutionTime = 30,
        int $maxRetries = 3,
        array $finishReasons = [],
    ) : ContinuationCriteria {
        return new ContinuationCriteria(
            new StepsLimit($maxSteps, fn(ToolUseState $state) => $state->stepCount()),
            new TokenUsageLimit($maxTokens, fn(ToolUseState $state) => $state->usage()->total()),
            new ExecutionTimeLimit($maxExecutionTime, fn(ToolUseState $state) => $state->startedAt()),
            new RetryLimit($maxRetries, fn(ToolUseState $state) => $state->steps(), fn(ToolUseStep $step) => $step->hasErrors()),
            new ErrorPresenceCheck(fn(ToolUseState $state) => $state->currentStep()?->hasErrors() ?? false),
            new ToolCallPresenceCheck(
                fn(ToolUseState $state) => $state->stepCount() === 0
                    ? true
                    : ($state->currentStep()?->hasToolCalls() ?? false)
            ),
            new FinishReasonCheck($finishReasons, fn(ToolUseState $state) => $state->currentStep()?->finishReason()),
        );
    }
}
