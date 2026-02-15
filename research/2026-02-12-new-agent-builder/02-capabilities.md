# Capability Inventory

## New Capabilities (extracted from AgentBuilder)

### UseGuards — execution safety limits

Replaces `$maxSteps`, `$maxTokens`, `$maxExecutionTime`, and the private `addGuardHooks()` method.

```php
final readonly class UseGuards implements AgentCapability
{
    public function __construct(
        private int $maxSteps = 20,
        private int $maxTokens = 32768,
        private int $timeout = 300,
        private array $finishReasons = [],
    ) {}

    public function install(AgentBuilder $builder): void
    {
        $builder->addHook(
            new StepsLimitHook(
                maxSteps: $this->maxSteps,
                stepCounter: static fn($state) => $state->stepCount(),
            ),
            HookTriggers::beforeStep(),
            priority: 200,
            name: 'guard:steps_limit',
        );

        $builder->addHook(
            new TokenUsageLimitHook(maxTotalTokens: $this->maxTokens),
            HookTriggers::beforeStep(),
            priority: 200,
            name: 'guard:token_limit',
        );

        $builder->addHook(
            new ExecutionTimeLimitHook(maxSeconds: $this->timeout),
            HookTriggers::with(HookTrigger::BeforeExecution, HookTrigger::BeforeStep),
            priority: 200,
            name: 'guard:time_limit',
        );

        if ($this->finishReasons !== []) {
            $builder->addHook(
                new FinishReasonHook(
                    $this->finishReasons,
                    static fn($state) => $state->currentStep()?->finishReason(),
                ),
                HookTriggers::afterStep(),
                priority: -200,
                name: 'finish_reason',
            );
        }
    }
}
```

**Rationale:** Guards are optional behavior-shaping hooks. They don't belong in the composition engine any more than summarization does. An agent without guards is perfectly valid (e.g., a one-shot Q&A agent that runs one step). Making guards a capability means you can:
- Skip them entirely for simple agents
- Swap in custom guard implementations
- Compose different guard sets for different agent profiles


### UseContextConfig — system prompt and response format

Replaces `$systemPrompt`, `$responseFormat`, and the private `addContextHooks()` method.

```php
final readonly class UseContextConfig implements AgentCapability
{
    public function __construct(
        private string $systemPrompt = '',
        private ?ResponseFormat $responseFormat = null,
    ) {}

    public function install(AgentBuilder $builder): void
    {
        $hasPrompt = $this->systemPrompt !== '';
        $hasFormat = $this->responseFormat !== null && !$this->responseFormat->isEmpty();

        if ($hasPrompt || $hasFormat) {
            $builder->addHook(
                new ApplyContextConfigHook($this->systemPrompt, $this->responseFormat),
                HookTriggers::beforeStep(),
                priority: 100,
                name: 'context:config',
            );
        }
    }
}
```

**Rationale:** System prompt is state — it belongs in `AgentContext` (part of `AgentState`). The `ApplyContextConfigHook` injects it into state before each step. This is a state-preparation hook, not a builder concern. Making it a capability keeps AgentBuilder stateless about what context the agent operates with.


### UseLlmConfig — driver construction

Replaces `$llmPreset`, `$maxRetries`, and the private `buildDefaultDriver()` method.

Note: context compiler is NOT part of this capability — it's a core builder primitive (see 01-architecture.md).

```php
final readonly class UseLlmConfig implements AgentCapability
{
    public function __construct(
        private ?string $preset = null,
        private int $maxRetries = 1,
    ) {}

    public function install(AgentBuilder $builder): void
    {
        $llmProvider = match ($this->preset) {
            null => LLMProvider::new(),
            default => LLMProvider::using($this->preset),
        };

        $retryPolicy = match (true) {
            $this->maxRetries > 1 => new InferenceRetryPolicy(maxAttempts: $this->maxRetries),
            default => null,
        };

        $driver = new ToolCallingDriver(
            llm: $llmProvider,
            messageCompiler: new ConversationWithCurrentToolTrace(), // placeholder — build() applies final compiler
            retryPolicy: $retryPolicy,
            events: $builder->eventHandler(),
        );

        $builder->withDriver($driver);
    }
}
```

**Rationale:** Driver construction is configuration, not composition. Today it's LLM preset + retry policy. Tomorrow it could include streaming config, token budgets, model fallbacks. Encapsulating it in a capability means driver configuration evolves independently of the builder.

**Note:** AgentBuilder still needs a fallback in `build()` for when no capability sets a driver. This provides the zero-config experience: `AgentBuilder::base()->build()` works without any capabilities.

**Compiler interaction:** During `build()`, the final context compiler (set via `withContextCompiler()` or default) is applied to the driver via `CanAcceptMessageCompiler::withMessageCompiler()`. This means `UseLlmConfig` doesn't need to know about the compiler — it sets up the driver, and `build()` applies the compiler afterwards. Capabilities that need custom compilation use `$builder->withContextCompiler()` independently.


## Existing Capabilities — unchanged

These capabilities are correctly scoped. They remain as-is:

| Capability | Current state | Future growth vector |
|---|---|---|
| **UseBash** | 1 tool | Security policies, command guards, output hooks, network policy |
| **UseFileTools** | 3 tools | Path guards, read-only mode, size limits, audit hooks |
| **UseToolRegistry** | 1 tool | Tool access policies, usage tracking |
| **UseTaskPlanning** | 1 tool + 1 hook | Task validation, priority hooks, reminders |
| **UseMetadataTools** | 3 tools + 1 hook | Encryption, size policies, namespace isolation |
| **UseSkills** | 1 tool + 1 hook | Skill versioning, access control, lazy loading |
| **UseSelfCritique** | 1 hook | Multi-evaluator chains, evaluation criteria |
| **UseStructuredOutputs** | 1 tool + 1 hook | Schema validation, output routing |
| **UseSummarization** | 2 hooks | Compression strategies, section policies |
| **UseSubagents** | 1 tool factory | Budget policies, depth strategies, tool filtering |


## Capability Composition Patterns

### Minimal agent (no capabilities)
```php
$loop = AgentBuilder::base()->build();
```
Gets: bare AgentLoop with default driver, no tools, no hooks, no guards.

### Standard agent (typical setup)
```php
$loop = AgentBuilder::base()
    ->withCapability(new UseGuards(maxSteps: 20))
    ->withCapability(new UseContextConfig(systemPrompt: 'You are a helpful assistant.'))
    ->withCapability(new UseBash())
    ->withCapability(new UseFileTools($workDir))
    ->build();
```

### Full-featured agent
```php
$loop = AgentBuilder::base()
    ->withCapability(new UseGuards(maxSteps: 50, maxTokens: 100000, timeout: 600))
    ->withCapability(new UseContextConfig(systemPrompt: $prompt))
    ->withCapability(new UseLlmConfig(preset: 'anthropic', maxRetries: 3))
    ->withCapability(new UseBash($bashPolicy))
    ->withCapability(new UseFileTools($workDir))
    ->withCapability(new UseMetadataTools())
    ->withCapability(new UseSkills($skillLibrary))
    ->withCapability(new UseStructuredOutputs($schemas))
    ->withCapability(new UseSummarization($summarizationPolicy))
    ->withCapability(new UseSubagents($agentProvider))
    ->withCapability(new UseSelfCritique(maxIterations: 2))
    ->withCapability(new UseTaskPlanning())
    ->build();
```

### Custom-only agent (user hooks, no packaged capabilities)
```php
$loop = AgentBuilder::base()
    ->addHook(new MyCustomGuard(), HookTriggers::beforeToolUse(), priority: 100)
    ->addHook(new MyAuditLogger(), HookTriggers::afterStep(), priority: -100)
    ->withTools([new MyCustomTool()])
    ->build();
```
Direct hook/tool registration remains available for one-off customization. Capabilities are not mandatory.
