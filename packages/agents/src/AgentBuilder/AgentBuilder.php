<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder;

use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Tools\BaseTool;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Agents\Events\CanAcceptAgentEventEmitter;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Agents\Hooks\Collections\HookTriggers;
use Cognesy\Agents\Hooks\Collections\RegisteredHooks;
use Cognesy\Agents\Hooks\Contracts\HookInterface;
use Cognesy\Agents\Hooks\Defaults\ApplyContextConfigHook;
use Cognesy\Agents\Hooks\Defaults\FinishReasonHook;
use Cognesy\Agents\Hooks\Enums\HookTrigger;
use Cognesy\Agents\Hooks\Guards\ExecutionTimeLimitHook;
use Cognesy\Agents\Hooks\Guards\StepsLimitHook;
use Cognesy\Agents\Hooks\Guards\TokenUsageLimitHook;
use Cognesy\Agents\Hooks\Interceptors\HookStack;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\LLMProvider;


/**
 * Fluent builder for creating Agent instances with composable features.
 *
 * @example
 * $agent = AgentBuilder::base()
 *     ->withCapability(new UseBash($policy))
 *     ->withCapability(new UseFileTools($baseDir))
 *     ->withCapability(new UseSkills($library))
 *     ->withCapability(new UseTaskPlanning())
 *     ->withMaxSteps(20)
 *     ->withMaxTokens(32768)
 *     ->build();
 */
class AgentBuilder
{
    private Tools $tools;

    private ?CanUseTools $driver = null;
    private ?CanHandleEvents $events = null;
    private ?string $llmPreset = null;
    private string $systemPrompt = '';
    private ?ResponseFormat $responseFormat = null;

    // Execution limits
    private int $maxSteps = 20;
    private int $maxTokens = 32768;
    private int $maxExecutionTime = 300;
    private int $maxRetries = 3;
    /** @var list<InferenceFinishReason> */
    private array $finishReasons = [];

    /** @var array<callable(Tools, CanUseTools, CanEmitAgentEvents): BaseTool> */
    private array $toolFactories = [];

    /** @var HookStack Unified hook stack for lifecycle and tool hooks */
    private HookStack $hookStack;

    private function __construct() {
        $this->tools = new Tools();
        $this->hookStack = new HookStack(new RegisteredHooks());
    }

    /**
     * Create a new builder instance.
     */
    public static function new(): self {
        return self::base();
    }

    /**
     * Create a new builder instance with sane defaults.
     */
    public static function base(): self {
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
     * Register a factory that produces a tool after driver/emitter are resolved.
     *
     * @param callable(Tools, CanUseTools, CanEmitAgentEvents): BaseTool $factory
     */
    public function addToolFactory(callable $factory): self {
        $this->toolFactories[] = $factory;
        return $this;
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

    /**
     * Set finish reasons that should stop the agent.
     * @param list<InferenceFinishReason> $reasons
     */
    public function withFinishReasons(array $reasons): self {
        $this->finishReasons = $reasons;
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

    public function withSystemPrompt(string $systemPrompt): self {
        $prompt = trim($systemPrompt);
        if ($prompt !== '') {
            $this->systemPrompt = $prompt;
        }
        return $this;
    }

    public function withResponseFormat(array|ResponseFormat $responseFormat): self {
        $this->responseFormat = match (true) {
            $responseFormat instanceof ResponseFormat => $responseFormat,
            is_array($responseFormat) => ResponseFormat::fromArray($responseFormat),
        };
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

    // HOOK REGISTRATION /////////////////////////////////////////

    /**
     * Add a hook to the agent.
     *
     * @param HookInterface $hook The hook to add
     * @param HookTriggers $triggers Triggers that execute the hook
     * @param int $priority Higher priority = runs earlier. Default is 0.
     *
     * @example
     * // Add a custom hook
     * $builder->addHook(new MyCustomHook(), HookTriggers::afterStep(), priority: 100);
     *
     * @example
     * // Add a callable hook for specific events
     * $builder->addHook(
     *     new MyCustomHook(),
     *     HookTriggers::beforeStep(),
     *     priority: 50,
     * );
     */
    public function addHook(HookInterface $hook, HookTriggers $triggers, int $priority = 0, ?string $name = null): self {
        $this->hookStack = $this->hookStack->with($hook, $triggers, $priority, $name);
        return $this;
    }

    /**
     * Get the current hook stack (for testing/debugging).
     */
    public function hookStack(): HookStack {
        return $this->hookStack;
    }

    // BUILD /////////////////////////////////////////////////////

    /**
     * Build the AgentLoop instance with configured features.
     */
    public function build(): AgentLoop {
        $eventEmitter = new AgentEventEmitter($this->events);
        $driver = $this->buildDriver($eventEmitter);

        // Resolve tool factories — each receives base tools + resolved driver/emitter
        $tools = $this->tools;
        foreach ($this->toolFactories as $factory) {
            $tool = $factory($tools, $driver, $eventEmitter);
            $tools = $tools->merge(new Tools($tool));
        }

        // Snapshot: base hooks + user hooks — never mutates $this->hookStack
        $interceptor = $this->buildHookStack();

        $toolExecutor = new ToolExecutor(
            tools: $tools,
            eventEmitter: $eventEmitter,
            interceptor: $interceptor,
            throwOnToolFailure: false,
        );

        return new AgentLoop(
            tools: $tools,
            toolExecutor: $toolExecutor,
            driver: $driver,
            eventEmitter: $eventEmitter,
            interceptor: $interceptor,
        );
    }

    // INTERNAL //////////////////////////////////////////////////

    /** Combine base hooks with user-registered hooks into a fresh stack. */
    private function buildHookStack(): HookStack {
        $stack = $this->hookStack;
        $stack = $this->addGuardHooks($stack);
        $stack = $this->addContextHooks($stack);
        $stack = $this->addMessageHooks($stack);
        return $stack;
    }

    private function addGuardHooks(HookStack $stack): HookStack {
        return $stack
            ->with(
                new StepsLimitHook(
                    maxSteps: $this->maxSteps,
                    stepCounter: static fn($state) => $state->stepCount(),
                ),
                HookTriggers::beforeStep(),
                200,
            )
            ->with(
                new TokenUsageLimitHook(
                    maxTotalTokens: $this->maxTokens,
                ),
                HookTriggers::beforeStep(),
                200,
            )
            ->with(
                new ExecutionTimeLimitHook(
                    maxSeconds: $this->maxExecutionTime,
                ),
                HookTriggers::with(HookTrigger::BeforeExecution, HookTrigger::BeforeStep),
                200,
            );
    }

    private function addContextHooks(HookStack $stack): HookStack {
        if ($this->systemPrompt === '' && ($this->responseFormat === null || $this->responseFormat->isEmpty())) {
            return $stack;
        }

        return $stack->with(
            new ApplyContextConfigHook($this->systemPrompt, $this->responseFormat),
            HookTriggers::beforeStep(),
            100,
        );
    }

    private function addMessageHooks(HookStack $stack): HookStack {
        return $stack->with(
            new FinishReasonHook(
                $this->finishReasons,
                static fn($state): ?InferenceFinishReason => $state->currentStep()?->finishReason()
            ),
            HookTriggers::afterStep(),
            -200,
        );
    }

    private function buildDriver(CanEmitAgentEvents $eventEmitter): CanUseTools {
        if ($this->driver !== null) {
            if ($this->driver instanceof CanAcceptAgentEventEmitter) {
                return $this->driver->withEventEmitter($eventEmitter);
            }
            return $this->driver;
        }

        $llmProvider = $this->llmPreset !== null
            ? LLMProvider::using($this->llmPreset)
            : LLMProvider::new();

        $retryPolicy = null;
        if ($this->maxRetries > 1) {
            $retryPolicy = new InferenceRetryPolicy(maxAttempts: $this->maxRetries);
        }

        return new ToolCallingDriver(
            llm: $llmProvider,
            retryPolicy: $retryPolicy,
            eventEmitter: $eventEmitter,
        );
    }

}
