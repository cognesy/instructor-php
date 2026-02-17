<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

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
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Continuation\ToolCallPresenceCheck;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

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

        return (new ToolUse(
            tools: $tools,
            toolExecutor: (new ToolExecutor($tools))->withEventHandler($events),
            processors: $processors ?? self::defaultProcessors(),
            continuationCriteria: $continuationCriteria ?? self::defaultContinuationCriteria(),
            driver: $driver ?? new ToolCallingDriver(
                inference: InferenceRuntime::fromProvider(
                    provider: LLMProvider::new(),
                    events: $events,
                ),
            ),
            events: $events,
        ));
    }

    public static function react(
        CanCreateInference $inference,
        CanCreateStructuredOutput $structuredOutput,
        ?Tools $tools = null,
        ?CanApplyProcessors $processors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanHandleEvents $events = null,
        string $model = '',
        array $options = [],
        bool $finalViaInference = false,
        ?string $finalModel = null,
        array $finalOptions = [],
    ): ToolUse {
        $events = EventBusResolver::using($events);
        $tools = $tools ?? new Tools();

        return new ToolUse(
            tools: $tools,
            toolExecutor: (new ToolExecutor($tools))->withEventHandler($events),
            processors: $processors ?? self::defaultProcessors(),
            continuationCriteria: $continuationCriteria ?? self::defaultContinuationCriteria(),
            driver: new ReActDriver(
                inference: $inference,
                structuredOutput: $structuredOutput,
                model: $model,
                options: $options,
                finalViaInference: $finalViaInference,
                finalModel: $finalModel,
                finalOptions: $finalOptions,
            ),
            events: $events,
        );
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
            new StepsLimit($maxSteps, static fn(ToolUseState $state) => $state->stepCount()),
            new TokenUsageLimit($maxTokens, static fn(ToolUseState $state) => $state->usage()->total()),
            new ExecutionTimeLimit($maxExecutionTime, static fn(ToolUseState $state) => $state->startedAt(), null),
            new RetryLimit($maxRetries, static fn(ToolUseState $state) => $state->steps(), static fn(ToolUseStep $step) => $step->hasErrors()),
            new ErrorPresenceCheck(static fn(ToolUseState $state) => $state->currentStep()?->hasErrors() ?? false),
            new ToolCallPresenceCheck(
                static fn(ToolUseState $state) => $state->stepCount() === 0
                    ? true
                    : ($state->currentStep()?->hasToolCalls() ?? false)
            ),
            new FinishReasonCheck($finishReasons, static fn(ToolUseState $state): ?InferenceFinishReason => $state->currentStep()?->finishReason()),
        );
    }
}
