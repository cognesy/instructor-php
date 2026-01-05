<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent;

use Cognesy\Addons\Agent\Agents\AgentRegistry;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Continuation\ToolCallPresenceCheck;
use Cognesy\Addons\Agent\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\AgentStep;
use Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\Agent\Skills\SkillLibrary;
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
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;

class AgentFactory
{
    /**
     * Get a fluent builder for creating agents.
     *
     * @example
     * $agent = AgentFactory::builder()
     *     ->withBash()
     *     ->withFileTools()
     *     ->withMaxSteps(20)
     *     ->build();
     */
    public static function builder(): AgentBuilder {
        return AgentBuilder::new();
    }

    /**
     * Create a basic agent with default configuration.
     *
     * @deprecated Use AgentFactory::builder() for better composability
     */
    public static function default(
        ?Tools $tools = null,
        ?CanApplyProcessors $processors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanUseTools $driver = null,
        ?CanHandleEvents $events = null,
        ?string $llmPreset = null,
    ): Agent {
        $events = EventBusResolver::using($events);
        $tools = $tools ?? new Tools();

        // Build driver with LLM preset if specified
        if ($driver === null) {
            $llmProvider = $llmPreset !== null
                ? LLMProvider::using($llmPreset)
                : LLMProvider::new();
            $driver = new ToolCallingDriver(llm: $llmProvider);
        }

        return (new Agent(
            tools: $tools,
            toolExecutor: (new ToolExecutor($tools))->withEventHandler($events),
            processors: $processors ?? self::defaultProcessors(),
            continuationCriteria: $continuationCriteria ?? self::defaultContinuationCriteria(),
            driver: $driver,
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

    // ENHANCED FACTORY METHODS ////////////////////////////////////////////

    /**
     * Create an agent with bash command execution capability.
     *
     * @deprecated Use AgentFactory::builder()->withBash()->build()
     */
    public static function withBash(
        ?ExecutionPolicy $policy = null,
        ?string $baseDir = null,
        int $timeout = 120,
        ?CanHandleEvents $events = null,
        ?string $llmPreset = null,
    ): Agent {
        return self::builder()
            ->withBash($policy, $baseDir, $timeout)
            ->withEvents($events ?? EventBusResolver::using(null))
            ->withLlmPreset($llmPreset ?? '')
            ->build();
    }

    /**
     * Create an agent with file operation tools (read, write, edit).
     *
     * @deprecated Use AgentFactory::builder()->withFileTools()->build()
     */
    public static function withFileTools(
        ?string $baseDir = null,
        ?CanHandleEvents $events = null,
        ?string $llmPreset = null,
    ): Agent {
        return self::builder()
            ->withFileTools($baseDir)
            ->withEvents($events ?? EventBusResolver::using(null))
            ->withLlmPreset($llmPreset ?? '')
            ->build();
    }

    /**
     * Create an agent with task planning capability (TodoWrite).
     *
     * @deprecated Use AgentFactory::builder()->withTaskPlanning()->build()
     */
    public static function withTaskPlanning(
        ?CanHandleEvents $events = null,
        ?string $llmPreset = null,
    ): Agent {
        return self::builder()
            ->withTaskPlanning()
            ->withEvents($events ?? EventBusResolver::using(null))
            ->withLlmPreset($llmPreset ?? '')
            ->build();
    }

    /**
     * Create an agent with skill loading capability.
     *
     * @deprecated Use AgentFactory::builder()->withSkills()->build()
     */
    public static function withSkills(
        ?SkillLibrary $library = null,
        ?CanHandleEvents $events = null,
        ?string $llmPreset = null,
    ): Agent {
        return self::builder()
            ->withSkills($library)
            ->withEvents($events ?? EventBusResolver::using(null))
            ->withLlmPreset($llmPreset ?? '')
            ->build();
    }

    /**
     * Create a full-featured coding agent with bash, file tools, task planning, and skills.
     *
     * @deprecated Use AgentFactory::builder() with fluent API for better composability
     *
     * @example
     * $agent = AgentFactory::builder()
     *     ->withBash($policy)
     *     ->withFileTools($workDir)
     *     ->withTaskPlanning()
     *     ->withSkills($library)
     *     ->withSubagents($registry, $maxDepth)
     *     ->withMaxSteps(20)
     *     ->withMaxTokens(32768)
     *     ->withTimeout(300)
     *     ->build();
     */
    public static function codingAgent(
        ?string $workDir = null,
        ?SkillLibrary $skills = null,
        ?AgentRegistry $subagentRegistry = null,
        int $maxSteps = 20,
        int $maxTokens = 32768,
        int $timeout = 300,
        int $maxSubagentDepth = 3,
        ?CanHandleEvents $events = null,
        ?string $llmPreset = null,
    ): Agent {
        $workDir = $workDir ?? getcwd() ?: '/tmp';
        $policy = ExecutionPolicy::in($workDir)
            ->withTimeout($timeout)
            ->withNetwork(true)
            ->withOutputCaps(5 * 1024 * 1024, 1 * 1024 * 1024)
            ->withReadablePaths($workDir)
            ->withWritablePaths($workDir)
            ->inheritEnvironment();

        $builder = self::builder()
            ->withBash($policy)
            ->withFileTools($workDir)
            ->withTaskPlanning()
            ->withSubagents($subagentRegistry, $maxSubagentDepth)
            ->withMaxSteps($maxSteps)
            ->withMaxTokens($maxTokens)
            ->withTimeout($timeout);

        if ($skills !== null) {
            $builder = $builder->withSkills($skills);
        }

        if ($events !== null) {
            $builder = $builder->withEvents($events);
        }

        if ($llmPreset !== null) {
            $builder = $builder->withLlmPreset($llmPreset);
        }

        return $builder->build();
    }
}
