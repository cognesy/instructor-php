# Usage Examples

## 1. Pluggable Guard Profiles

The capability model lets you define agent profiles as reusable guard sets.

```php
// Tight guard for untrusted inputs
$tightGuards = new UseGuards(maxSteps: 5, maxTokens: 4096, timeout: 30);

// Relaxed guard for internal batch processing
$batchGuards = new UseGuards(maxSteps: 100, maxTokens: 500000, timeout: 3600);

// Quick Q&A — no guards at all
$qaAgent = AgentBuilder::base()
    ->withCapability(new UseContextConfig(systemPrompt: 'Answer concisely.'))
    ->build();

// Interactive assistant — tight limits
$interactiveAgent = AgentBuilder::base()
    ->withCapability($tightGuards)
    ->withCapability(new UseContextConfig(systemPrompt: $assistantPrompt))
    ->withCapability(new UseBash())
    ->build();

// Background processor — relaxed limits
$batchAgent = AgentBuilder::base()
    ->withCapability($batchGuards)
    ->withCapability(new UseFileTools($dataDir))
    ->withCapability(new UseStructuredOutputs($schemas))
    ->withCapability(new UseSummarization())
    ->build();
```

**Key insight:** Guard configuration is a policy decision, not a builder default. Different agent profiles need different limits. Making guards a capability makes this composition natural — not a special case.


## 2. Modular Feature Composition

Capabilities are independent. Add or remove features without touching other configuration.

```php
// Start with base agent
$builder = AgentBuilder::base()
    ->withCapability(new UseGuards(maxSteps: 20))
    ->withCapability(new UseContextConfig(systemPrompt: $prompt))
    ->withCapability(new UseBash());

// Need file access? Add it.
$builder->withCapability(new UseFileTools($workDir));

// Need structured extraction? Add it.
$builder->withCapability(new UseStructuredOutputs($schemas));

// Need long-running context management? Add it.
$builder->withCapability(new UseSummarization());

$loop = $builder->build();
```

Each capability is self-contained. `UseStructuredOutputs` doesn't know about `UseSummarization`. They compose through hooks at declared priorities.


## 3. Custom Capabilities — Extend Without Forking

User-defined capabilities use the same interface as built-in ones.

```php
final readonly class UseRateLimit implements AgentCapability
{
    public function __construct(
        private int $maxCallsPerMinute = 60,
    ) {}

    public function install(AgentBuilder $builder): void
    {
        $builder->addHook(
            new RateLimitHook($this->maxCallsPerMinute),
            HookTriggers::beforeStep(),
            priority: 190,  // just below guards
            name: 'guard:rate_limit',
        );
    }
}

// Use it like any built-in capability
$loop = AgentBuilder::base()
    ->withCapability(new UseGuards(maxSteps: 50))
    ->withCapability(new UseRateLimit(maxCallsPerMinute: 30))
    ->withCapability(new UseBash())
    ->build();
```


## 4. Capability Growth — Why Wrappers Pay Off

`UseBash` is one tool today. It grows without changing call sites.

```php
// Today
final class UseBash implements AgentCapability
{
    public function install(AgentBuilder $builder): void
    {
        $builder->withTools(new Tools(new BashTool($this->policy)));
    }
}

// Tomorrow — added security guard, audit hook, output sanitizer
final class UseBash implements AgentCapability
{
    public function install(AgentBuilder $builder): void
    {
        $builder->withTools(new Tools(new BashTool($this->policy)));

        $builder->addHook(
            new BashCommandGuard($this->securityPolicy),
            HookTriggers::beforeToolUse(),
            priority: 150,
            name: 'bash:command_guard',
        );

        $builder->addHook(
            new BashAuditHook($this->auditPolicy),
            HookTriggers::afterToolUse(),
            priority: -50,
            name: 'bash:audit',
        );

        $builder->addHook(
            new BashOutputSanitizer($this->sanitizationPolicy),
            HookTriggers::afterToolUse(),
            priority: -100,
            name: 'bash:sanitize_output',
        );
    }
}
```

Every call site (`->withCapability(new UseBash())`) automatically gets the new guards. Zero migration.


## 5. Avoiding AgentLoop Bloat

The capability model keeps AgentLoop thin by pushing all optional behavior into composable packages.

**Without the capability model (hypothetical bloated AgentLoop):**
```php
// Everything hardcoded in AgentLoop
$loop = AgentLoop::default()
    ->withMaxSteps(20)           // guard logic in the loop
    ->withMaxTokens(32768)       // guard logic in the loop
    ->withTimeout(300)           // guard logic in the loop
    ->withSystemPrompt($prompt)  // state injection in the loop
    ->withRetryPolicy(3)         // driver config in the loop
    ->withSummarization(...)     // context management in the loop
    ->withSelfCritique(...)      // evaluation logic in the loop
    ->withTool(new BashTool());
```

Problems:
- AgentLoop becomes stateful (system prompt, guards, driver config)
- Every new feature adds methods to AgentLoop
- Features can't be packaged/shared independently
- Hook priority ordering becomes implicit and fragile
- Testing requires mocking the monolith

**With the capability model (actual design):**
```php
$loop = AgentBuilder::base()
    ->withCapability(new UseGuards(maxSteps: 20, maxTokens: 32768, timeout: 300))
    ->withCapability(new UseContextConfig(systemPrompt: $prompt))
    ->withCapability(new UseLlmConfig(maxRetries: 3))
    ->withCapability(new UseSummarization())
    ->withCapability(new UseSelfCritique())
    ->withCapability(new UseBash())
    ->build();
```

Benefits:
- AgentLoop stays a stateless engine — ~400 lines, unchanged as features grow
- Each capability is independently testable
- Hook priorities are declared by capabilities, not hardcoded in the builder
- New features = new capability classes, never touch existing code
- Capabilities can be shared as packages across projects


## 6. Preset Bundles — Composing Capabilities

Common capability combinations can be bundled as composite capabilities.

```php
final readonly class UseCodingAgent implements AgentCapability
{
    public function __construct(
        private string $workDir,
        private string $systemPrompt = 'You are a coding assistant.',
    ) {}

    public function install(AgentBuilder $builder): void
    {
        (new UseGuards(maxSteps: 30, maxTokens: 64000, timeout: 600))->install($builder);
        (new UseContextConfig(systemPrompt: $this->systemPrompt))->install($builder);
        (new UseBash())->install($builder);
        (new UseFileTools($this->workDir))->install($builder);
        (new UseTaskPlanning())->install($builder);
        (new UseSummarization())->install($builder);
    }
}

// One-liner for a fully configured coding agent
$loop = AgentBuilder::base()
    ->withCapability(new UseCodingAgent('/project'))
    ->build();
```

Composite capabilities compose like any other. The `install()` contract is recursive — a capability can install other capabilities.


## 7. Before/After Comparison — AgentBuilder Refactoring

### Before (current)
```php
$agent = AgentBuilder::base()
    ->withLlmPreset('anthropic')
    ->withMaxSteps(20)
    ->withMaxTokens(32768)
    ->withTimeout(300)
    ->withMaxRetries(3)
    ->withSystemPrompt('You are a helpful assistant.')
    ->withCapability(new UseBash())
    ->withCapability(new UseFileTools($workDir))
    ->withCapability(new UseSummarization())
    ->build();
```

Mixed API: some config is builder methods, some is capabilities. No consistency about where configuration lives.

### After (target)
```php
$agent = AgentBuilder::base()
    ->withCapability(new UseLlmConfig(preset: 'anthropic', maxRetries: 3))
    ->withCapability(new UseGuards(maxSteps: 20, maxTokens: 32768, timeout: 300))
    ->withCapability(new UseContextConfig(systemPrompt: 'You are a helpful assistant.'))
    ->withCapability(new UseBash())
    ->withCapability(new UseFileTools($workDir))
    ->withCapability(new UseSummarization())
    ->build();

```

Uniform API: everything is a capability. Builder has no config-specific methods.
