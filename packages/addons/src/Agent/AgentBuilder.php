<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent;

use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Continuation\ToolCallPresenceCheck;
use Cognesy\Addons\Agent\Core\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\StateProcessing\Processors\ApplyCachedContext;
use Cognesy\Addons\Agent\Core\ToolExecutor;
use Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPolicyCriterion;
use Cognesy\Addons\StepByStep\Continuation\Criteria\CumulativeExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\Continuation\ErrorPolicy;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendContextMetadata;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Messages;
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

    /** @var CanDecideToContinue[] Flat list of continuation criteria */
    private array $criteria = [];

    private ?CanUseTools $driver = null;
    private ?CanHandleEvents $events = null;
    private ?string $llmPreset = null;
    private ?CachedContext $cachedContext = null;
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
    public function addContinuationCriteria(CanDecideToContinue $criteria): self {
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

    /** @return StateProcessors<AgentState> */
    private function buildProcessors(): StateProcessors {
        $baseProcessors = [];
        if ($this->cachedContext !== null && !$this->cachedContext->isEmpty()) {
            $baseProcessors[] = new ApplyCachedContext($this->cachedContext);
        }
        $baseProcessors[] = new AccumulateTokenUsage();
        $baseProcessors[] = new AppendContextMetadata();
        $baseProcessors[] = new AppendStepMessages();

        $allProcessors = array_merge($baseProcessors, $this->processors);

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

    private function buildTimeLimitCriterion(): CanDecideToContinue {
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

        // Pass maxRetries to inference via retryPolicy options
        $options = [];
        if ($this->maxRetries > 1) {
            $options['retryPolicy'] = ['maxAttempts' => $this->maxRetries];
        }

        return new ToolCallingDriver(llm: $llmProvider, options: $options);
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
}
