<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder;

use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Contracts\CanAcceptMessageCompiler;
use Cognesy\Agents\Core\Contracts\CanCompileMessages;
use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;
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
use Cognesy\Events\Dispatchers\EventDispatcher;
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
final class AgentBuilder
{
    private Tools $tools;

    private ?CanUseTools $driver = null;
    private ?CanCompileMessages $contextCompiler = null;
    private CanHandleEvents $events;
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
        $this->events = new EventDispatcher('agent-builder');
        $this->hookStack = new HookStack(new RegisteredHooks());
    }

    public static function base(): self {
        return new self();
    }

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

    public function withTools(Tools|array $tools): self {
        $toolsCollection = match(true) {
            is_array($tools) => new Tools(...$tools),
            $tools instanceof Tools => $tools,
        };

        $this->tools = $this->tools->merge($toolsCollection);
        return $this;
    }

    // EXECUTION LIMITS //////////////////////////////////////////

    public function withMaxSteps(int $maxSteps): self {
        $this->maxSteps = $maxSteps;
        return $this;
    }

    public function withMaxTokens(int $maxTokens): self {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function withTimeout(int $seconds): self {
        $this->maxExecutionTime = $seconds;
        return $this;
    }

    public function withMaxRetries(int $maxRetries): self {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    /** @param list<InferenceFinishReason> $reasons */
    public function withFinishReasons(array $reasons): self {
        $this->finishReasons = $reasons;
        return $this;
    }

    // LLM CONFIGURATION /////////////////////////////////////////

    public function withLlmPreset(string $preset): self {
        $this->llmPreset = $preset;
        return $this;
    }

    public function withDriver(CanUseTools $driver): self {
        $this->driver = $driver;
        return $this;
    }

    public function withContextCompiler(CanCompileMessages $compiler): self {
        $this->contextCompiler = $compiler;
        return $this;
    }

    public function withSystemPrompt(string $systemPrompt): self {
        $prompt = trim($systemPrompt);
        $this->systemPrompt = match(true) {
            $prompt !== '' => $prompt,
            default => $this->systemPrompt,
        };
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
     * Set a parent event handler for event bubbling.
     * Call this BEFORE adding capabilities to ensure capability listeners are preserved.
     */
    public function withEvents(CanHandleEvents $events): self {
        $this->events = new EventDispatcher('agent-builder', $events);
        return $this;
    }

    /**
     * Access the event handler for registering listeners.
     * Capabilities use this to observe agent events.
     */
    public function eventHandler(): CanHandleEvents {
        return $this->events;
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

    public function hookStack(): HookStack {
        return $this->hookStack;
    }

    // BUILD /////////////////////////////////////////////////////

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
        $interceptor = $this->buildHookStack($eventEmitter);

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
    private function buildHookStack(CanEmitAgentEvents $eventEmitter): HookStack {
        $stack = new HookStack(
            hooks: new RegisteredHooks(),
            onHookExecuted: fn(string $triggerType, ?string $hookName, \DateTimeImmutable $startedAt)
                => $eventEmitter->hookExecuted($triggerType, $hookName, $startedAt),
        );
        // Re-register all hooks from the builder's stack into the new wired stack
        foreach ($this->hookStack->hooks() as $hook) {
            $stack = $stack->withHook($hook);
        }
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
                'guard:steps_limit',
            )
            ->with(
                new TokenUsageLimitHook(
                    maxTotalTokens: $this->maxTokens,
                ),
                HookTriggers::beforeStep(),
                200,
                'guard:token_limit',
            )
            ->with(
                new ExecutionTimeLimitHook(
                    maxSeconds: $this->maxExecutionTime,
                ),
                HookTriggers::with(HookTrigger::BeforeExecution, HookTrigger::BeforeStep),
                200,
                'guard:time_limit',
            );
    }

    private function addContextHooks(HookStack $stack): HookStack {
        $hasPrompt = $this->systemPrompt !== '';
        $hasFormat = $this->responseFormat !== null && !$this->responseFormat->isEmpty();

        return match(true) {
            $hasPrompt, $hasFormat => $stack->with(
                new ApplyContextConfigHook($this->systemPrompt, $this->responseFormat),
                HookTriggers::beforeStep(),
                100,
                'context:config',
            ),
            default => $stack,
        };
    }

    private function addMessageHooks(HookStack $stack): HookStack {
        return $stack->with(
            new FinishReasonHook(
                $this->finishReasons,
                static fn($state): ?InferenceFinishReason => $state->currentStep()?->finishReason()
            ),
            HookTriggers::afterStep(),
            -200,
            'finish_reason',
        );
    }

    private function buildDriver(CanEmitAgentEvents $eventEmitter): CanUseTools {
        $driver = match(true) {
            $this->driver instanceof CanAcceptAgentEventEmitter => $this->driver->withEventEmitter($eventEmitter),
            $this->driver !== null => $this->driver,
            default => $this->buildDefaultDriver($eventEmitter),
        };

        $driver = match (true) {
            $this->contextCompiler === null => $driver,
            $driver instanceof CanAcceptMessageCompiler => $driver->withMessageCompiler($this->contextCompiler),
            default => $driver,
        };

        return $driver;
    }

    private function buildDefaultDriver(CanEmitAgentEvents $eventEmitter): ToolCallingDriver {
        $llmProvider = match($this->llmPreset) {
            null => LLMProvider::new(),
            default => LLMProvider::using($this->llmPreset),
        };

        $retryPolicy = match(true) {
            $this->maxRetries > 1 => new InferenceRetryPolicy(maxAttempts: $this->maxRetries),
            default => null,
        };

        return new ToolCallingDriver(
            llm: $llmProvider,
            messageCompiler: $this->contextCompiler ?? new ConversationWithCurrentToolTrace(),
            retryPolicy: $retryPolicy,
            eventEmitter: $eventEmitter,
        );
    }

}
