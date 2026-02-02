<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder;

use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Agents\Hooks\Defaults\AppendContextMetadataHook;
use Cognesy\Agents\Hooks\Defaults\AppendFinalResponseHook;
use Cognesy\Agents\Hooks\Defaults\AppendStepMessagesHook;
use Cognesy\Agents\Hooks\Defaults\AppendToolTraceToBufferHook;
use Cognesy\Agents\Hooks\Defaults\ApplyCachedContextHook;
use Cognesy\Agents\Hooks\Defaults\ClearExecutionBufferHook;
use Cognesy\Agents\Hooks\Defaults\ErrorPolicyHook;
use Cognesy\Agents\Hooks\Defaults\FinishReasonHook;
use Cognesy\Agents\Hooks\Guards\ExecutionTimeLimitHook;
use Cognesy\Agents\Hooks\Guards\StepsLimitHook;
use Cognesy\Agents\Hooks\Guards\TokenUsageLimitHook;
use Cognesy\Agents\Hooks\HookInterface;
use Cognesy\Agents\Hooks\HookStack;
use Cognesy\Agents\Hooks\HookTrigger;
use Cognesy\Agents\Hooks\HookTriggers;
use Cognesy\Agents\Hooks\RegisteredHooks;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\LLMProvider;
use tmp\ErrorHandling\AgentErrorHandler;
use tmp\ErrorHandling\ErrorPolicy;

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
    private ?CachedInferenceContext $cachedContext = null;
    private ?ErrorPolicy $errorPolicy = null;
    private bool $separateToolTrace = true;

    // Execution limits
    private int $maxSteps = 20;
    private int $maxTokens = 32768;
    private int $maxExecutionTime = 300;
    private int $maxRetries = 3;
    /** @var list<InferenceFinishReason> */
    private array $finishReasons = [];

    /** @var array<callable(AgentLoop): AgentLoop> */
    private array $onBuildCallbacks = [];

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
        return new self();
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
     * Register a callback to be executed after the agent loop is built.
     * The callback receives the built AgentLoop and must return an AgentLoop (potentially modified).
     *
     * @param callable(AgentLoop): AgentLoop $callback
     */
    public function onBuild(callable $callback): self {
        $this->onBuildCallbacks[] = $callback;
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

    public function withSeparatedToolTrace(bool $enabled = true): self {
        $this->separateToolTrace = $enabled;
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

    public function withErrorPolicy(ErrorPolicy $policy): self {
        $this->errorPolicy = $policy;
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

    public function withCachedContext(CachedInferenceContext $cachedContext): self {
        $this->cachedContext = $cachedContext;
        return $this;
    }

    public function withSystemPrompt(string $systemPrompt): self {
        $prompt = trim($systemPrompt);
        if ($prompt === '') {
            return $this;
        }

        $cache = $this->cachedContext ?? new CachedInferenceContext();
        $messages = $cache->messages();
        if ($this->hasSystemPrompt($messages, $prompt)) {
            return $this;
        }

        $prepended = Messages::fromArray([
            ['role' => 'system', 'content' => $prompt],
        ])->appendMessages($messages);

        $this->cachedContext = new CachedInferenceContext(
            messages: $prepended->toArray(),
            tools: $cache->tools(),
            toolChoice: $cache->toolChoice(),
            responseFormat: $this->responseFormatToArray($cache->responseFormat()),
        );

        return $this;
    }

    public function withResponseFormat(array $responseFormat): self {
        $cache = $this->cachedContext ?? new CachedInferenceContext();
        $this->cachedContext = new CachedInferenceContext(
            messages: $cache->messages()->toArray(),
            tools: $cache->tools(),
            toolChoice: $cache->toolChoice(),
            responseFormat: $responseFormat,
        );
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
        // Register base hooks (core functionality)
        $this->registerBaseHooks();

        // Build event emitter FIRST (shared between all components)
        $eventEmitter = new AgentEventEmitter($this->events);

        // Build driver with shared event emitter
        $driver = $this->buildDriver($eventEmitter);

        $interceptor = $this->hookStack;

        // Build tool executor with lifecycle interceptor
        $toolExecutor = new ToolExecutor(
            tools: $this->tools,
            eventEmitter: $eventEmitter,
            interceptor: $interceptor,
            throwOnToolFailure: false,
        );

        // Build error handler
        $errorHandler = AgentErrorHandler::withPolicy(
            policy: $this->errorPolicy ?? ErrorPolicy::stopOnAnyError(),
        );

        // Build agent loop (hooks handle everything via interceptor)
        $agent = new AgentLoop(
            tools: $this->tools,
            toolExecutor: $toolExecutor,
            errorHandler: $errorHandler,
            driver: $driver,
            eventEmitter: $eventEmitter,
            interceptor: $interceptor,
        );

        foreach ($this->onBuildCallbacks as $callback) {
            $agent = $callback($agent);
        }

        return $agent;
    }

    // INTERNAL //////////////////////////////////////////////////

    /**
     * Register base hooks for core agent functionality.
     *
     * These hooks handle:
     * - Guard limits (steps, tokens, time) via BeforeStep stop signals
     * - Cached context application (before step)
     * - Message history management (after step)
     * - Tool trace separation (after step)
     */
    private function registerBaseHooks(): void {
        // BeforeStep: Guard hooks for execution limits (priority 200 = runs first)
        $this->hookStack = $this->hookStack->with(
            new StepsLimitHook(
                maxSteps: $this->maxSteps,
                stepCounter: static fn($state) => $state->stepCount(),
            ),
            HookTriggers::beforeStep(),
            200,
        );

        $this->hookStack = $this->hookStack->with(
            new TokenUsageLimitHook(
                maxTotalTokens: $this->maxTokens,
            ),
            HookTriggers::beforeStep(),
            200,
        );

        $this->hookStack = $this->hookStack->with(
            new ExecutionTimeLimitHook(
                maxSeconds: $this->maxExecutionTime,
            ),
            HookTriggers::with(HookTrigger::BeforeExecution, HookTrigger::BeforeStep),
            200,
        );

        // BeforeStep: Apply cached context (priority 100 = runs after guards)
        if ($this->cachedContext !== null && !$this->cachedContext->isEmpty()) {
            $this->hookStack = $this->hookStack->with(
                new ApplyCachedContextHook($this->cachedContext),
                HookTriggers::beforeStep(),
                100,
            );
        }

        // AfterStep: Message history management (priority -100 = runs late)
        $this->hookStack = $this->hookStack->with(new AppendContextMetadataHook(), HookTriggers::afterStep(), -100);

        if ($this->separateToolTrace) {
            $this->hookStack = $this->hookStack->with(new ClearExecutionBufferHook(), HookTriggers::afterStep(), -110);
            $this->hookStack = $this->hookStack->with(new AppendFinalResponseHook(), HookTriggers::afterStep(), -120);
            $this->hookStack = $this->hookStack->with(new AppendToolTraceToBufferHook(), HookTriggers::afterStep(), -130);
        } else {
            $this->hookStack = $this->hookStack->with(new AppendStepMessagesHook(), HookTriggers::afterStep(), -100);
        }

        $this->hookStack = $this->hookStack->with(
            new ErrorPolicyHook($this->errorPolicy ?? ErrorPolicy::stopOnAnyError()),
            HookTriggers::afterStep(),
            -200,
        );

        $this->hookStack = $this->hookStack->with(
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
            // If custom driver provided, try to inject event emitter
            if (method_exists($this->driver, 'withEventEmitter')) {
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

    private function hasSystemPrompt(Messages $messages, string $prompt): bool {
        foreach ($messages->messageList()->all() as $message) {
            if ($message->role()->value !== 'system') {
                continue;
            }
            $content = $message->content()->toString();
            if ($content === $prompt) {
                return true;
            }
        }
        return false;
    }

    private function responseFormatToArray(ResponseFormat $format): array {
        return match(true) {
            $format->isEmpty() => [],
            default => [
                'type' => $format->type(),
                'schema' => $format->schema(),
                'name' => $format->schemaName(),
                'strict' => $format->strict(),
            ],
        };
    }
}
