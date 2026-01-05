<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent;

use Cognesy\Addons\Agent\Agents\AgentRegistry;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Continuation\ToolCallPresenceCheck;
use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\Agent\Extras\Tasks\TodoPolicy;
use Cognesy\Addons\Agent\Skills\SkillLibrary;
use Cognesy\Addons\Agent\Tools\BashPolicy;
use Cognesy\Addons\Agent\Tools\Subagent\SubagentPolicy;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPresenceCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\RetryLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendContextMetadata;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;

/**
 * Fluent builder for creating Agent instances with composable features.
 *
 * @example
 * $agent = AgentBuilder::new()
 *     ->withBash($policy)
 *     ->withFileTools($baseDir)
 *     ->withSkills($library)
 *     ->withTaskPlanning()
 *     ->withMaxSteps(20)
 *     ->withMaxTokens(32768)
 *     ->build();
 */
class AgentBuilder
{
    private Tools $tools;
    private array $processors = [];
    private array $continuationCriteria = [];
    private ?CanUseTools $driver = null;
    private ?CanHandleEvents $events = null;
    private ?string $llmPreset = null;

    // Execution limits
    private int $maxSteps = 20;
    private int $maxTokens = 32768;
    private int $maxExecutionTime = 300;
    private int $maxRetries = 3;
    private array $finishReasons = [];

    /** @var callable[] */
    private array $onBuildCallbacks = [];

    private function __construct() {
        $this->tools = new Tools();
    }

    /**
     * Create a new builder instance.
     */
    public static function new(): self {
        return new self();
    }

    /**
     * Apply a capability to the builder.
     */
    public function withCapability(AgentCapability $capability): self {
        $capability->install($this);
        return $this;
    }

    /**
     * Register a callback to be executed after the agent is built.
     * The callback receives the built Agent and must return an Agent (potentially modified).
     *
     * @param callable(Agent): Agent $callback
     */
    public function onBuild(callable $callback): self {
        $this->onBuildCallbacks[] = $callback;
        return $this;
    }

    // TOOL CONFIGURATION ////////////////////////////////////////

    /**
     * Add bash command execution capability.
     */
    public function withBash(
        ?ExecutionPolicy $policy = null,
        ?string $baseDir = null,
        int $timeout = 120,
        ?BashPolicy $outputPolicy = null,
    ): self {
        return $this->withCapability(new Capabilities\UseBash(
            policy: $policy,
            baseDir: $baseDir,
            timeout: $timeout,
            outputPolicy: $outputPolicy
        ));
    }

    /**
     * Add file operation tools (read, write, edit).
     */
    public function withFileTools(?string $baseDir = null): self {
        return $this->withCapability(new Capabilities\UseFileTools($baseDir));
    }

    /**
     * Add skill loading capability.
     */
    public function withSkills(?SkillLibrary $library = null): self {
        return $this->withCapability(new Capabilities\UseSkills($library));
    }

    /**
     * Add task planning capability (TodoWrite).
     */
    public function withTaskPlanning(?TodoPolicy $policy = null): self {
        return $this->withCapability(new Capabilities\UseTaskPlanning($policy));
    }

    /**
     * Enable subagent spawning capability.
     */
    public function withSubagents(
        ?AgentRegistry $registry = null,
        int|SubagentPolicy $policyOrDepth = 3,
        ?int $summaryMaxChars = null,
        ?SkillLibrary $skillLibrary = null,
    ): self {
        return $this->withCapability(new Capabilities\UseSubagents(
            registry: $registry,
            policyOrDepth: $policyOrDepth,
            summaryMaxChars: $summaryMaxChars,
            skillLibrary: $skillLibrary,
        ));
    }

    /**
     * Add custom tools.
     */
    public function withTools(Tools|array $tools): self {
        $toolsCollection = match(true) {
            is_array($tools) => new Tools(...$tools),
            $tools instanceof Tools => $tools,
        };

        $this->tools = $this->tools->merge($toolsCollection);
        return $this;
    }

    public function addProcessor(CanProcessAnyState $processor): self {
        $this->processors[] = $processor;
        return $this;
    }

    public function addContinuationCriteria(CanDecideToContinue $criteria): self {
        $this->continuationCriteria[] = $criteria;
        return $this;
    }

    // EXECUTION LIMITS //////////////////////////////////////////

    /**
     * Set maximum number of agent steps.
     */
    public function withMaxSteps(int $maxSteps): self {
        $this->maxSteps = $maxSteps;
        return $this;
    }

    /**
     * Set maximum token usage.
     */
    public function withMaxTokens(int $maxTokens): self {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    /**
     * Set maximum execution time in seconds.
     */
    public function withTimeout(int $seconds): self {
        $this->maxExecutionTime = $seconds;
        return $this;
    }

    /**
     * Set maximum retry attempts on errors.
     */
    public function withMaxRetries(int $maxRetries): self {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    // LLM CONFIGURATION /////////////////////////////////////////

    /**
     * Set LLM preset (from config/llm.php).
     */
    public function withLlmPreset(string $preset): self {
        $this->llmPreset = $preset;
        return $this;
    }

    /**
     * Set custom driver.
     */
    public function withDriver(CanUseTools $driver): self {
        $this->driver = $driver;
        return $this;
    }

    // EVENT HANDLING ////////////////////////////////////////////

    /**
     * Set event handler.
     */
    public function withEvents(CanHandleEvents $events): self {
        $this->events = $events;
        return $this;
    }

    // BUILD /////////////////////////////////////////////////////

    /**
     * Build the Agent instance with configured features.
     */
    public function build(): Agent {
        // Build processors
        $processors = $this->buildProcessors();

        // Build continuation criteria
        $continuationCriteria = $this->buildContinuationCriteria();

        // Build driver
        $driver = $this->buildDriver();

        // Build base agent
        $agent = new Agent(
            tools: $this->tools,
            toolExecutor: (new ToolExecutor($this->tools))->withEventHandler(
                EventBusResolver::using($this->events)
            ),
            processors: $processors,
            continuationCriteria: $continuationCriteria,
            driver: $driver,
            events: $this->events,
        );

        foreach ($this->onBuildCallbacks as $callback) {
            $agent = $callback($agent);
        }

        return $agent;
    }

    // INTERNAL //////////////////////////////////////////////////

    private function buildProcessors(): StateProcessors {
        $baseProcessors = [
            new AccumulateTokenUsage(),
            new AppendContextMetadata(),
            new AppendStepMessages(),
        ];

        $allProcessors = array_merge($baseProcessors, $this->processors);

        /** @var StateProcessors<AgentState> $processors */
        $processors = new StateProcessors(...$allProcessors);
        return $processors;
    }

    private function buildContinuationCriteria(): ContinuationCriteria {
        $baseCriteria = [
            new StepsLimit($this->maxSteps, static fn($state) => $state->stepCount()),
            new TokenUsageLimit($this->maxTokens, static fn($state) => $state->usage()->total()),
            new ExecutionTimeLimit($this->maxExecutionTime, static fn($state) => $state->startedAt(), null),
            new RetryLimit($this->maxRetries, static fn($state) => $state->steps(), static fn($step) => $step->hasErrors()),
            new ErrorPresenceCheck(static fn($state) => $state->currentStep()?->hasErrors() ?? false),
            new ToolCallPresenceCheck(
                static fn($state) => $state->stepCount() === 0 || (($state->currentStep()?->hasToolCalls() ?? false))
            ),
            new FinishReasonCheck($this->finishReasons, static fn($state): ?InferenceFinishReason => $state->currentStep()?->finishReason()),
        ];

        $allCriteria = array_merge($baseCriteria, $this->continuationCriteria);

        return new ContinuationCriteria(...$allCriteria);
    }

    private function buildDriver(): CanUseTools {
        if ($this->driver !== null) {
            return $this->driver;
        }

        $llmProvider = $this->llmPreset !== null
            ? LLMProvider::using($this->llmPreset)
            : LLMProvider::new();

        return new ToolCallingDriver(llm: $llmProvider);
    }
}
