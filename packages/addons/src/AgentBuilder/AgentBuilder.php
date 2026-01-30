<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder;

use Closure;
use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Continuation\ToolCallPresenceCheck;
use Cognesy\Addons\Agent\Core\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\StateProcessing\Processors\ApplyCachedContext;
use Cognesy\Addons\Agent\Core\ToolExecutor;
use Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\Agent\Hooks\Contracts\Hook;
use Cognesy\Addons\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Addons\Agent\Hooks\Data\ExecutionHookContext;
use Cognesy\Addons\Agent\Hooks\Data\FailureHookContext;
use Cognesy\Addons\Agent\Hooks\Data\HookEvent;
use Cognesy\Addons\Agent\Hooks\Data\HookOutcome;
use Cognesy\Addons\Agent\Hooks\Data\StopHookContext;
use Cognesy\Addons\Agent\Hooks\Hooks\AfterToolHook;
use Cognesy\Addons\Agent\Hooks\Hooks\AgentFailedHook;
use Cognesy\Addons\Agent\Hooks\Hooks\BeforeToolHook;
use Cognesy\Addons\Agent\Hooks\Hooks\CallableHook;
use Cognesy\Addons\Agent\Hooks\Hooks\ExecutionEndHook;
use Cognesy\Addons\Agent\Hooks\Hooks\ExecutionStartHook;
use Cognesy\Addons\Agent\Hooks\Hooks\StopHook;
use Cognesy\Addons\Agent\Hooks\Hooks\SubagentStopHook;
use Cognesy\Addons\Agent\Hooks\Matchers\ToolNameMatcher;
use Cognesy\Addons\Agent\Hooks\Stack\HookStack;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\CumulativeExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPolicyCriterion;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\ErrorHandling\ErrorPolicy;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendContextMetadata;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\CallableProcessor;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
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
    private array $processors = [];
    private array $preProcessors = [];

    /** @var CanEvaluateContinuation[] Flat list of continuation criteria */
    private array $criteria = [];

    private ?CanUseTools $driver = null;
    private ?CanHandleEvents $events = null;
    private ?string $llmPreset = null;
    private ?CachedInferenceContext $cachedContext = null;
    private ?ErrorPolicy $errorPolicy = null;

    // Execution limits
    private int $maxSteps = 20;
    private int $maxTokens = 32768;
    private int $maxExecutionTime = 300;
    private int $maxRetries = 3;
    private array $finishReasons = [];
    private ?int $cumulativeTimeout = null;

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

    public function addProcessor(CanProcessAnyState $processor): self {
        $this->processors[] = $processor;
        return $this;
    }

    public function addPreProcessor(CanProcessAnyState $processor): self {
        $this->preProcessors[] = $processor;
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

    public function withCumulativeTimeout(int $seconds): self {
        $this->cumulativeTimeout = $seconds;
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

    // TOOL HOOKS ////////////////////////////////////////////////

    /**
     * Register a callback to run before tool execution.
     *
     * The callback receives ToolHookContext and can:
     * - Return HookOutcome::proceed() to allow the tool call
     * - Return HookOutcome::proceed($modifiedContext) to modify the tool call
     * - Return HookOutcome::block($reason) to prevent the tool call
     * - Return null to block the tool call (legacy support)
     * - Return a ToolCall to proceed with a modified call (legacy support)
     * - Return void to proceed with the original call
     *
     * @param callable(ToolHookContext): (HookOutcome|ToolCall|null|void) $callback
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
     * The callback receives ToolHookContext and can:
     * - Return HookOutcome::proceed() to continue unchanged
     * - Return HookOutcome::proceed($modifiedContext) to modify the execution result
     * - Return HookOutcome::stop($reason) to halt agent execution
     * - Return an AgentExecution to replace the result (legacy support)
     * - Return anything else (or void) to keep the original result
     *
     * @param callable(ToolHookContext): (HookOutcome|AgentExecution|mixed) $callback
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
            position: 'before',
            stateClass: AgentState::class,
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
            position: 'after',
            stateClass: AgentState::class,
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
     * @param HookEvent $event The lifecycle event to hook into
     * @param callable|Hook $hook The hook callback or Hook instance
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
        HookEvent $event,
        callable|Hook $hook,
        int $priority = 0,
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
     * @param callable(ExecutionHookContext): (HookOutcome|void) $callback
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
     * @param callable(ExecutionHookContext): (HookOutcome|void) $callback
     * @param int $priority Higher priority = runs earlier. Default is 0.
     *
     * @example
     * $builder->onExecutionEnd(function (ExecutionHookContext $ctx): void {
     *     $this->metrics->stopTracking($ctx->state()->agentId);
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
     * @param callable(StopHookContext): (HookOutcome|void) $callback
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
     * @param callable(StopHookContext): (HookOutcome|void) $callback
     * @param int $priority Higher priority = runs earlier. Default is 0.
     *
     * @example
     * $builder->onSubagentStop(function (StopHookContext $ctx): void {
     *     $this->logger->info("Subagent completed: {$ctx->state()->agentId}");
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
     * @param callable(FailureHookContext): (HookOutcome|void) $callback
     * @param int $priority Higher priority = runs earlier. Default is 0.
     *
     * @example
     * $builder->onAgentFailed(function (FailureHookContext $ctx): void {
     *     $this->logger->error("Agent failed: {$ctx->errorMessage()}");
     *     $this->alerting->sendAlert($ctx->exception());
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

        // Build driver
        $driver = $this->buildDriver();

        // Build tool executor with unified hook stack
        $toolExecutor = new ToolExecutor(
            tools: $this->tools,
            throwOnToolFailure: false,
            events: EventBusResolver::using($this->events),
            toolHookStack: $this->hookStack,
        );

        // Build base agent
        $agent = new Agent(
            tools: $this->tools,
            toolExecutor: $toolExecutor,
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

    /** @return StateProcessors<AgentState> */
    private function buildProcessors(): StateProcessors {
        $baseProcessors = [];
        if ($this->cachedContext !== null && !$this->cachedContext->isEmpty()) {
            $baseProcessors[] = new ApplyCachedContext($this->cachedContext);
        }
        $baseProcessors[] = new AccumulateTokenUsage();
        $baseProcessors[] = new AppendContextMetadata();
        $baseProcessors[] = new AppendStepMessages();

        $allProcessors = array_merge($this->preProcessors, $baseProcessors, $this->processors);

        /** @var StateProcessors<AgentState> */
        return new StateProcessors(...$allProcessors);
    }

    private function buildContinuationCriteria(): ContinuationCriteria {
        // Base criteria - all in flat list with priority-based resolution
        // Hard limits return ForbidContinuation when exceeded, AllowStop otherwise
        // Continue signals return AllowContinuation when they have work, AllowStop otherwise
        $baseCriteria = [
            // Hard limits (return ForbidContinuation when exceeded)
            new StepsLimit($this->maxSteps, static fn($state) => $state->stepCount()),
            new TokenUsageLimit($this->maxTokens, static fn($state) => $state->usage()->total()),
            // Uses executionStartedAt (set at start of each execution) with fallback to session startedAt.
            // This prevents timeouts in multi-turn conversations spanning days.
            $this->buildTimeLimitCriterion(),
            ErrorPolicyCriterion::withPolicy($this->errorPolicy ?? ErrorPolicy::stopOnAnyError()),
            new FinishReasonCheck($this->finishReasons, static fn($state): ?InferenceFinishReason => $state->currentStep()?->finishReason()),
            // Continue signal (returns AllowContinuation when tool calls present)
            new ToolCallPresenceCheck(
                static fn($state) => $state->stepCount() === 0 || ($state->currentStep()?->hasToolCalls() ?? false)
            ),
        ];

        // Merge with user-added criteria
        $allCriteria = [...$baseCriteria, ...$this->criteria];

        return ContinuationCriteria::from(...$allCriteria);
    }

    private function buildTimeLimitCriterion(): CanEvaluateContinuation {
        if ($this->cumulativeTimeout !== null) {
            return new CumulativeExecutionTimeLimit(
                $this->cumulativeTimeout,
                static fn(AgentState $state): float => $state->stateInfo()->cumulativeExecutionSeconds(),
            );
        }

        return new ExecutionTimeLimit(
            $this->maxExecutionTime,
            static fn(AgentState $state): \DateTimeImmutable => $state->executionStartedAt() ?? $state->startedAt(),
            null,
        );
    }

    private function buildDriver(): CanUseTools {
        if ($this->driver !== null) {
            return $this->driver;
        }

        $llmProvider = $this->llmPreset !== null
            ? LLMProvider::using($this->llmPreset)
            : LLMProvider::new();

        $retryPolicy = null;
        if ($this->maxRetries > 1) {
            $retryPolicy = new InferenceRetryPolicy(maxAttempts: $this->maxRetries);
        }

        return new ToolCallingDriver(llm: $llmProvider, retryPolicy: $retryPolicy);
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
