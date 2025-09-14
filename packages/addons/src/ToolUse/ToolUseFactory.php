<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\Core\Contracts\CanApplyProcessors;
use Cognesy\Addons\Core\StateProcessors;
use Cognesy\Addons\ToolUse\ContinuationCriteria\ErrorPresenceCheck;
use Cognesy\Addons\ToolUse\ContinuationCriteria\ExecutionTimeLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\FinishReasonCheck;
use Cognesy\Addons\ToolUse\ContinuationCriteria\RetryLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\TokenUsageLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\ToolCallPresenceCheck;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\Collections\ContinuationCriteria;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Processors\AccumulateTokenUsage;
use Cognesy\Addons\ToolUse\Processors\AppendContextMetadata;
use Cognesy\Addons\ToolUse\Processors\AppendToolStateMessages;
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
        $tools = $tools->withEventHandler($events);

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
            new AppendToolStateMessages(),
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
            new StepsLimit($maxSteps),
            new TokenUsageLimit($maxTokens),
            new ExecutionTimeLimit($maxExecutionTime),
            new RetryLimit($maxRetries),
            new ErrorPresenceCheck(),
            new ToolCallPresenceCheck(),
            new FinishReasonCheck($finishReasons),
        );
    }
}