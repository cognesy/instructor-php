<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent;

use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Continuation\ToolCallPresenceCheck;
use Cognesy\Addons\Agent\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\AgentStep;
use Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\Agent\Skills\SkillLibrary;
use Cognesy\Addons\Agent\StateProcessors\PersistTasksProcessor;
use Cognesy\Addons\Agent\Subagents\AgentCapability;
use Cognesy\Addons\Agent\Subagents\SubagentRegistry;
use Cognesy\Addons\Agent\Tools\BashTool;
use Cognesy\Addons\Agent\Tools\File\EditFileTool;
use Cognesy\Addons\Agent\Tools\File\ReadFileTool;
use Cognesy\Addons\Agent\Tools\File\WriteFileTool;
use Cognesy\Addons\Agent\Tools\LoadSkillTool;
use Cognesy\Addons\Agent\Tools\Subagent\SpawnSubagentTool;
use Cognesy\Addons\Agent\Tools\TodoWriteTool;
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
     */
    public static function withBash(
        ?ExecutionPolicy $policy = null,
        ?string $baseDir = null,
        int $timeout = 120,
        ?CanHandleEvents $events = null,
        ?string $llmPreset = null,
    ): Agent {
        $bashTool = new BashTool(policy: $policy, baseDir: $baseDir, timeout: $timeout);
        return self::default(tools: new Tools($bashTool), events: $events, llmPreset: $llmPreset);
    }

    /**
     * Create an agent with file operation tools (read, write, edit).
     */
    public static function withFileTools(
        ?string $baseDir = null,
        ?CanHandleEvents $events = null,
        ?string $llmPreset = null,
    ): Agent {
        $baseDir = $baseDir ?? getcwd() ?: '/tmp';

        $tools = new Tools(
            ReadFileTool::inDirectory($baseDir),
            WriteFileTool::inDirectory($baseDir),
            EditFileTool::inDirectory($baseDir),
        );

        return self::default(tools: $tools, events: $events, llmPreset: $llmPreset);
    }

    /**
     * Create an agent with task planning capability (TodoWrite).
     */
    public static function withTaskPlanning(
        ?CanHandleEvents $events = null,
        ?string $llmPreset = null,
    ): Agent {
        $tools = new Tools(new TodoWriteTool());

        $processors = new StateProcessors(
            new AccumulateTokenUsage(),
            new AppendContextMetadata(),
            new AppendStepMessages(),
            new PersistTasksProcessor(),
        );

        return self::default(tools: $tools, processors: $processors, events: $events, llmPreset: $llmPreset);
    }

    /**
     * Create an agent with skill loading capability.
     */
    public static function withSkills(
        ?SkillLibrary $library = null,
        ?CanHandleEvents $events = null,
        ?string $llmPreset = null,
    ): Agent {
        $library = $library ?? new SkillLibrary();
        $tools = new Tools(LoadSkillTool::withLibrary($library));

        return self::default(tools: $tools, events: $events, llmPreset: $llmPreset);
    }

    /**
     * Create a full-featured coding agent with bash, file tools, task planning, and skills.
     *
     * @param string|null $workDir Working directory for file operations
     * @param SkillLibrary|null $skills Skill library for skill loading
     * @param SubagentRegistry|null $subagentRegistry Registry of available subagents (null = create empty)
     * @param int $maxSteps Maximum number of agent steps
     * @param int $maxTokens Maximum token usage
     * @param int $timeout Execution timeout in seconds
     * @param int $maxSubagentDepth Maximum nesting depth for subagents (default: 3)
     * @param CanHandleEvents|null $events Event handler
     * @param string|null $llmPreset LLM preset name from config/llm.php
     * @return Agent
     */
    public static function codingAgent(
        ?string $workDir = null,
        ?SkillLibrary $skills = null,
        ?SubagentRegistry $subagentRegistry = null,
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

        $tools = new Tools(
            new BashTool(policy: $policy),
            ReadFileTool::withPolicy($policy),
            WriteFileTool::withPolicy($policy),
            EditFileTool::withPolicy($policy),
            new TodoWriteTool(),
        );

        if ($skills !== null) {
            $tools = $tools->merge(new Tools(LoadSkillTool::withLibrary($skills)));
        }

        $processors = new StateProcessors(
            new AccumulateTokenUsage(),
            new AppendContextMetadata(),
            new AppendStepMessages(),
            new PersistTasksProcessor(),
        );

        $continuationCriteria = self::defaultContinuationCriteria(
            maxSteps: $maxSteps,
            maxTokens: $maxTokens,
            maxExecutionTime: $timeout,
        );

        $agent = self::default(
            tools: $tools,
            processors: $processors,
            continuationCriteria: $continuationCriteria,
            events: $events,
            llmPreset: $llmPreset,
        );

        // Create subagent registry if not provided
        $subagentRegistry = $subagentRegistry ?? new SubagentRegistry();

        // Get LLM provider for subagent inheritance
        $llmProvider = $llmPreset !== null
            ? LLMProvider::using($llmPreset)
            : LLMProvider::new();

        // Add subagent tool with registry
        $subagentTool = new SpawnSubagentTool(
            parentAgent: $agent,
            registry: $subagentRegistry,
            skillLibrary: $skills,
            parentLlmProvider: $llmProvider,
            currentDepth: 0,
            maxDepth: $maxSubagentDepth,
        );

        return $agent->withTools($tools->merge(new Tools($subagentTool)));
    }
}
