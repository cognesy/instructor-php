<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent;

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPresenceCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\RetryLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\StateProcessing\CanApplyProcessors;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendContextMetadata;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Continuation\ToolCallPresenceCheck;
use Cognesy\Addons\Agent\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\AgentStep;
use Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

class AgentFactory
{
    public static function default(
        ?Tools $tools = null,
        ?CanApplyProcessors $processors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanUseTools $driver = null,
        ?CanHandleEvents $events = null,
    ): Agent {
        $events = EventBusResolver::using($events);
        $tools = $tools ?? new Tools();

        return (new Agent(
            tools: $tools,
            toolExecutor: (new ToolExecutor($tools))->withEventHandler($events),
            processors: $processors ?? self::defaultProcessors(),
            continuationCriteria: $continuationCriteria ?? self::defaultContinuationCriteria(),
            driver: $driver ?? new ToolCallingDriver,
            events: $events,
        ));
    }

    protected static function defaultProcessors(): CanApplyProcessors {
        /** @psalm-suppress InvalidArgument - Processors have different TState constraints but work via canProcess() runtime check */
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
            new StepsLimit($maxSteps, static fn(AgentState $state) => $state->stepCount()),
            new TokenUsageLimit($maxTokens, static fn(AgentState $state) => $state->usage()->total()),
            new ExecutionTimeLimit($maxExecutionTime, static fn(AgentState $state) => $state->startedAt(), null),
            new RetryLimit($maxRetries, static fn(AgentState $state) => $state->steps(), static fn(AgentStep $step) => $step->hasErrors()),
            new ErrorPresenceCheck(static fn(AgentState $state) => $state->currentStep()?->hasErrors() ?? false),
            new ToolCallPresenceCheck(
                static fn(AgentState $state) => $state->stepCount() === 0 || (($state->currentStep()?->hasToolCalls() ?? false))
            ),
            new FinishReasonCheck($finishReasons, static fn(AgentState $state): ?InferenceFinishReason => $state->currentStep()?->finishReason()),
        );
    }
}
