<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder;

use Closure;
use Cognesy\Agents\Agent\Agent;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Agents\Agent\StateProcessing\Processors\AppendContextMetadata;
use Cognesy\Agents\Agent\StateProcessing\Processors\AppendFinalResponse;
use Cognesy\Agents\Agent\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Agents\Agent\StateProcessing\Processors\AppendToolTraceToBuffer;
use Cognesy\Agents\Agent\StateProcessing\Processors\ApplyCachedContext;
use Cognesy\Agents\Agent\StateProcessing\Processors\CallableProcessor;
use Cognesy\Agents\Agent\StateProcessing\Processors\ClearExecutionBuffer;
use Cognesy\Agents\Agent\StateProcessing\StateProcessors;
use Cognesy\Agents\Agent\Hooks\HookStackObserver;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Agent\Hooks\Contracts\Hook;
use Cognesy\Agents\Agent\Hooks\Contracts\HookContext;
use Cognesy\Agents\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Agents\Agent\Hooks\Data\ExecutionHookContext;
use Cognesy\Agents\Agent\Hooks\Data\FailureHookContext;
use Cognesy\Agents\Agent\Hooks\Data\HookOutcome;
use Cognesy\Agents\Agent\Hooks\Data\StopHookContext;
use Cognesy\Agents\Agent\Hooks\Data\ToolHookContext;
use Cognesy\Agents\Agent\Hooks\Enums\HookType;
use Cognesy\Agents\Agent\Hooks\Hooks\AfterToolHook;
use Cognesy\Agents\Agent\Hooks\Hooks\AgentFailedHook;
use Cognesy\Agents\Agent\Hooks\Hooks\BeforeToolHook;
use Cognesy\Agents\Agent\Hooks\Hooks\CallableHook;
use Cognesy\Agents\Agent\Hooks\Hooks\ExecutionEndHook;
use Cognesy\Agents\Agent\Hooks\Hooks\ExecutionStartHook;
use Cognesy\Agents\Agent\Hooks\Hooks\StopHook;
use Cognesy\Agents\Agent\Hooks\Hooks\SubagentStopHook;
use Cognesy\Agents\Agent\Hooks\Matchers\ToolNameMatcher;
use Cognesy\Agents\Agent\Hooks\Stack\HookStack;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Continuation\Contracts\CanEvaluateContinuation;
use Cognesy\Agents\Core\Continuation\Criteria\ErrorPolicyCriterion;
use Cognesy\Agents\Core\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Agents\Core\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Agents\Core\Continuation\Criteria\StepsLimit;
use Cognesy\Agents\Core\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Agents\Core\Continuation\Criteria\ToolCallPresenceCheck;
use Cognesy\Agents\Core\Contracts\CanEmitAgentEvents;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\ErrorHandling\AgentErrorHandler;
use Cognesy\Agents\Core\ErrorHandling\ErrorPolicy;
use Cognesy\Agents\Core\Events\AgentEventEmitter;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\CachedContext;
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
    private array $processors = [];
    private array $preProcessors = [];

    /** @var CanEvaluateContinuation[] Flat list of continuation criteria */
    private array $criteria = [];

    private ?CanUseTools $driver = null;
    private ?CanHandleEvents $events = null;
    private ?string $llmPreset = null;
    private ?CachedContext $cachedContext = null;
    private ?ErrorPolicy $errorPolicy = null;
    private bool $separateToolTrace = true;

    // Execution limits
    private int $maxSteps = 20;
    private int $maxTokens = 32768;
    private int $maxExecutionTime = 300;
    private int $maxRetries = 3;
    /** @var list<InferenceFinishReason> */
    private array $finishReasons = [];

    /** @var array<callable(Agent): Agent> */
    private array $onBuildCallbacks = [];

    /** @var HookStack Unified hook stack for lifecycle and tool hooks */
    private HookStack $hookStack;

    private function __construct() {
        $this->tools = new Tools();
        $this->hookStack = new HookStack();
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
     * Register a callback to be executed after the agent is built.
     * The callback receives the built Agent and must return an Agent (potentially modified).
     *
     * @param callable(Agent): Agent $callback
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

    public function addProcessor(CanProcessAgentState $processor): self {
        $this->processors[] = $processor;
        return $this;
    }

    public function addPreProcessor(CanProcessAgentState $processor): self {
        $this->preProcessors[] = $processor;
        return $this;
    }

    public function withSeparatedToolTrace(bool $enabled = true): self {
        $this->separateToolTrace = $enabled;
        return $this;
    }

    /**
     * Add continuation criterion to the flat criteria list.
     *
     * Resolution uses priority logic (order-independent):
     *   - ForbidContinuation from any criterion → STOP (hard limits win)
     *   - AllowContinuation from any criterion → CONTINUE (someone has work)
     *   - AllowStop from all criteria → STOP (nothing to do)
     *
     * Hard limits (StepsLimit, TokenUsageLimit) return ForbidContinuation when exceeded.
     * Continue signals (ToolCallPresence, SelfCritic) return AllowContinuation when they have work.
     */
    public function addContinuationCriteria(CanEvaluateContinuation $criteria): self {
        $this->criteria[] = $criteria;
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

    public function withCachedContext(CachedContext $cachedContext): self {
        $this->cachedContext = $cachedContext;
        return $this;
    }

    public function withSystemPrompt(string $systemPrompt): self {
        $prompt = trim($systemPrompt);
        if ($prompt === '') {
            return $this;
        }

        $cache = $this->cachedContext ?? new CachedContext();
        $messages = $cache->messages();
        if ($this->hasSystemPrompt($messages, $prompt)) {
            return $this;
        }

        $prepended = Messages::fromArray([
            ['role' => 'system', 'content' => $prompt],
        ])->appendMessages($messages);

        $this->cachedContext = new CachedContext(
            messages: $prepended->toArray(),
            tools: $cache->tools(),
            toolChoice: $cache->toolChoice(),
            responseFormat: $this->responseFormatToArray($cache->responseFormat()),
        );

        return $this;
    }

    public function withResponseFormat(array $responseFormat): self {
        $cache = $this->cachedContext ?? new CachedContext();
        $this->cachedContext = new CachedContext(
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

    // TOOL HOOKS ////////////////////////////////////////////////

    /**
     * Register a callback to run before tool execution.
     *
     * The callback receives ToolHookContext and can:
     * - Return HookOutcome::proceed() to allow the tool call
     * - Return HookOutcome::proceed($ctx->withToolCall($modified)) to modify the tool call
     * - Return HookOutcome::block($reason) to prevent the tool call
     * - Return HookOutcome::stop($reason) to halt agent execution
     *
     * @param callable(ToolHookContext): HookOutcome $callback
     * @param int $priority Higher priority = runs earlier. Default is 0.
     * @param string|HookMatcher|null $matcher Tool name pattern or custom matcher
     *
     * @example
     * // Block dangerous bash commands
     * $builder->onBeforeToolUse(
     *     callback: function (ToolHookContext $ctx): HookOutcome {
     *         $command = $ctx->toolCall()->args()['command'] ?? '';
     *         if (str_contains($command, 'rm -rf')) {
     *             return HookOutcome::block('Dangerous command blocked');
     *         }
     *         return HookOutcome::proceed();
     *     },
     *     matcher: 'bash',
     *     priority: 100,
     * );
     */
    public function onBeforeToolUse(
        callable $callback,
        int $priority = 0,
        string|HookMatcher|null $matcher = null,
    ): self {
        $matcherObj = $this->resolveMatcher($matcher);
        $hook = new BeforeToolHook(
            Closure::fromCallable($callback),
            $matcherObj,
        );
        $this->hookStack = $this->hookStack->with($hook, $priority);
        return $this;
    }

    /**
     * Register a callback to run after tool execution.
     *
     * The callback must return a HookOutcome:
     * - HookOutcome::proceed() to continue unchanged
     * - HookOutcome::proceed($ctx->withExecution($modified)) to modify the execution result
     * - HookOutcome::stop($reason) to halt agent execution
     *
     * @param callable(ToolHookContext): HookOutcome $callback
     * @param int $priority Higher priority = runs earlier. Default is 0.
     * @param string|HookMatcher|null $matcher Tool name pattern or custom matcher
     *
     * @example
     * // Log all tool executions
     * $builder->onAfterToolUse(
     *     callback: function (ToolHookContext $ctx): HookOutcome {
     *         $execution = $ctx->execution();
     *         $this->logger->info("Tool {$ctx->toolCall()->name()} completed");
     *         return HookOutcome::proceed();
     *     },
     *     priority: -100,
     * );
     */
    public function onAfterToolUse(
        callable $callback,
        int $priority = 0,
        string|HookMatcher|null $matcher = null,
    ): self {
        $matcherObj = $this->resolveMatcher($matcher);
        $hook = new AfterToolHook(
            Closure::fromCallable($callback),
            $matcherObj,
        );
        $this->hookStack = $this->hookStack->with($hook, $priority);
        return $this;
    }

    // STEP HOOKS ////////////////////////////////////////////////

    /**
     * Register a callback to run before each step.
     *
     * The callback receives the AgentState and must return an AgentState.
     *
     * @param callable(AgentState): AgentState $callback
     *
     * @example
     * $builder->onBeforeStep(fn(AgentState $state) => $state->withMetadata('step_started', microtime(true)));
     */
    public function onBeforeStep(callable $callback): self {
        $this->preProcessors[] = new CallableProcessor(
            Closure::fromCallable($callback),
        );
        return $this;
    }

    /**
     * Register a callback to run after each step.
     *
     * The callback receives the AgentState and must return an AgentState.
     *
     * @param callable(AgentState): AgentState $callback
     *
     * @example
     * $builder->onAfterStep(function (AgentState $state) {
     *     $duration = microtime(true) - $state->metadata('step_started');
     *     $this->metrics->recordStepDuration($duration);
     *     return $state;
     * });
     */
    public function onAfterStep(callable $callback): self {
        $this->processors[] = new CallableProcessor(
            Closure::fromCallable($callback),
        );
        return $this;
    }

    // UNIFIED HOOK REGISTRATION //////////////////////////////////

    /**
     * Register a hook for a specific lifecycle event.
     *
     * This is the unified hook registration method that works with the
     * new Hook system. For convenience, use the event-specific methods
     * like onExecutionStart(), onStop(), etc.
     *
     * @param HookType $event The lifecycle event to hook into
     * @param (callable(HookContext, callable(HookContext): HookOutcome): HookOutcome)|Hook $hook The hook callback or Hook instance
     * @param int $priority Higher priority = runs earlier. Default is 0.
     * @param string|HookMatcher|null $matcher Optional matcher for conditional execution
     *
     * @example
     * $builder->addHook(
     *     event: HookEvent::ExecutionStart,
     *     hook: fn(ExecutionHookContext $ctx) => HookOutcome::proceed(),
     *     priority: 100,
     * );
     */
    public function addHook(
        HookType                $event,
        callable|Hook           $hook,
        int                     $priority = 0,
        string|HookMatcher|null $matcher = null,
    ): self {
        $hookInstance = $hook instanceof Hook
            ? $hook
            : new CallableHook(
                Closure::fromCallable($hook),
                $this->resolveMatcher($matcher),
            );

        $this->hookStack = $this->hookStack->with($hookInstance, $priority);
        return $this;
    }

    /**
     * Register a callback to run when agent execution starts.
     *
     * Fired once when agent.run() begins, before any steps are executed.
     *
     * @param callable(ExecutionHookContext): HookOutcome $callback
     * @param int $priority Higher priority = runs earlier. Default is 0.
     *
     * @example
     * $builder->onExecutionStart(function (ExecutionHookContext $ctx): HookOutcome {
     *     $this->metrics->startTracking($ctx->state()->agentId);
     *     return HookOutcome::proceed();
     * });
     */
    public function onExecutionStart(callable $callback, int $priority = 0): self {
        $this->hookStack = $this->hookStack->with(
            new ExecutionStartHook(Closure::fromCallable($callback)),
            $priority,
        );
        return $this;
    }

    /**
     * Register a callback to run when agent execution ends.
     *
     * Fired once when agent.run() completes (success or failure).
     *
     * @param callable(ExecutionHookContext): HookOutcome $callback
     * @param int $priority Higher priority = runs earlier. Default is 0.
     *
     * @example
     * $builder->onExecutionEnd(function (ExecutionHookContext $ctx): HookOutcome {
     *     $this->metrics->stopTracking($ctx->state()->agentId);
     *     return HookOutcome::proceed();
     * });
     */
    public function onExecutionEnd(callable $callback, int $priority = 0): self {
        $this->hookStack = $this->hookStack->with(
            new ExecutionEndHook(Closure::fromCallable($callback)),
            $priority,
        );
        return $this;
    }

    /**
     * Register a callback to run when agent is about to stop.
     *
     * Can block the stop to force continuation.
     *
     * @param callable(StopHookContext): HookOutcome $callback
     * @param int $priority Higher priority = runs earlier. Default is 0.
     *
     * @example
     * $builder->onStop(function (StopHookContext $ctx): HookOutcome {
     *     if ($this->hasUnfinishedWork($ctx->state())) {
     *         return HookOutcome::block('Work remaining');
     *     }
     *     return HookOutcome::proceed();
     * });
     */
    public function onStop(callable $callback, int $priority = 0): self {
        $this->hookStack = $this->hookStack->with(
            new StopHook(Closure::fromCallable($callback)),
            $priority,
        );
        return $this;
    }

    /**
     * Register a callback to run when a subagent is about to stop.
     *
     * @param callable(StopHookContext): HookOutcome $callback
     * @param int $priority Higher priority = runs earlier. Default is 0.
     *
     * @example
     * $builder->onSubagentStop(function (StopHookContext $ctx): HookOutcome {
     *     $this->logger->info("Subagent completed: {$ctx->state()->agentId}");
     *     return HookOutcome::proceed();
     * });
     */
    public function onSubagentStop(callable $callback, int $priority = 0): self {
        $this->hookStack = $this->hookStack->with(
            new SubagentStopHook(Closure::fromCallable($callback)),
            $priority,
        );
        return $this;
    }

    /**
     * Register a callback to run when agent fails.
     *
     * Fired when the agent encounters an unrecoverable error.
     *
     * @param callable(FailureHookContext): HookOutcome $callback
     * @param int $priority Higher priority = runs earlier. Default is 0.
     *
     * @example
     * $builder->onAgentFailed(function (FailureHookContext $ctx): HookOutcome {
     *     $this->logger->error("Agent failed: {$ctx->errorMessage()}");
     *     $this->alerting->sendAlert($ctx->exception());
     *     return HookOutcome::proceed();
     * });
     */
    public function onAgentFailed(callable $callback, int $priority = 0): self {
        $this->hookStack = $this->hookStack->with(
            new AgentFailedHook(Closure::fromCallable($callback)),
            $priority,
        );
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
     * Build the Agent instance with configured features.
     */
    public function build(): Agent {
        // Build processors
        $processors = $this->buildProcessors();

        // Build continuation criteria
        $continuationCriteria = $this->buildContinuationCriteria();

        // Build event emitter FIRST (shared between all components)
        $eventEmitter = new AgentEventEmitter($this->events);

        // Build driver with shared event emitter
        $driver = $this->buildDriver($eventEmitter);

        // Build lifecycle observer from hook stack
        $observer = new HookStackObserver(
            hookStack: $this->hookStack,
            eventEmitter: $eventEmitter,
        );

        // Build tool executor with lifecycle observer
        $toolExecutor = new ToolExecutor(
            tools: $this->tools,
            throwOnToolFailure: false,
            eventEmitter: $eventEmitter,
            observer: $observer,
        );

        // Build error handler
        $errorHandler = AgentErrorHandler::withPolicy(
            policy: $this->errorPolicy ?? ErrorPolicy::stopOnAnyError(),
        );

        // Build base agent
        $agent = new Agent(
            tools: $this->tools,
            toolExecutor: $toolExecutor,
            errorHandler: $errorHandler,
            processors: $processors,
            continuationCriteria: $continuationCriteria,
            driver: $driver,
            eventEmitter: $eventEmitter,
            observer: $observer,
        );

        foreach ($this->onBuildCallbacks as $callback) {
            $agent = $callback($agent);
        }

        return $agent;
    }

    // INTERNAL //////////////////////////////////////////////////

    private function buildProcessors(): StateProcessors {
        $baseProcessors = [];
        if ($this->cachedContext !== null && !$this->cachedContext->isEmpty()) {
            $baseProcessors[] = new ApplyCachedContext($this->cachedContext);
        }
        $baseProcessors[] = new AppendContextMetadata();
        if ($this->separateToolTrace) {
            $baseProcessors[] = new ClearExecutionBuffer();
            $baseProcessors[] = new AppendFinalResponse();
            $baseProcessors[] = new AppendToolTraceToBuffer();
        } else {
            $baseProcessors[] = new AppendStepMessages();
        }

        $allProcessors = array_merge($this->preProcessors, $baseProcessors, $this->processors);

        return new StateProcessors(...$allProcessors);
    }

    private function buildContinuationCriteria(): ContinuationCriteria {
        // Base criteria - all in flat list with priority-based resolution
        // Hard limits return ForbidContinuation when exceeded, AllowStop otherwise
        // Continue signals return AllowContinuation when they have work, AllowStop otherwise
        //
        // Note: Criteria are evaluated BEFORE the step is recorded to stepExecutions,
        // but currentStep is already set. transientStepCount() accounts for this
        // without double-counting already recorded steps.
        $baseCriteria = [
            // Hard limits (return ForbidContinuation when exceeded)
            new StepsLimit($this->maxSteps, static fn($state) => $state->transientStepCount()),
            new TokenUsageLimit($this->maxTokens, static fn($state) => $state->usage()->total()),
            // Per-execution time limit (optional).
            $this->buildTimeLimitCriterion(),
            ErrorPolicyCriterion::withPolicy($this->errorPolicy ?? ErrorPolicy::stopOnAnyError()),
            new FinishReasonCheck($this->finishReasons, static fn($state): ?InferenceFinishReason => $state->currentStep()?->finishReason()),
            // Continue signal - returns RequestContinuation when:
            // - Bootstrap: no step executed yet (currentStep is null) - allows first step
            // - Work to do: current step contains tool calls
            new ToolCallPresenceCheck(
                static fn($state) => $state->currentStep() === null
                    || $state->currentStep()->hasToolCalls()
            ),
        ];

        // Merge with user-added criteria
        $allCriteria = [...$baseCriteria, ...$this->criteria];

        return ContinuationCriteria::from(...$allCriteria);
    }

    private function buildTimeLimitCriterion(): CanEvaluateContinuation {
        return new ExecutionTimeLimit(
            $this->maxExecutionTime,
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
        return $format->isEmpty()
            ? []
            : [
                'type' => $format->type(),
                'schema' => $format->schema(),
                'name' => $format->schemaName(),
                'strict' => $format->strict(),
            ];
    }

    /**
     * Convert a string pattern or HookMatcher to a HookMatcher instance.
     */
    private function resolveMatcher(string|HookMatcher|null $matcher): ?HookMatcher {
        if ($matcher === null) {
            return null;
        }

        if ($matcher instanceof HookMatcher) {
            return $matcher;
        }

        // String = tool name pattern
        return new ToolNameMatcher($matcher);
    }
}
