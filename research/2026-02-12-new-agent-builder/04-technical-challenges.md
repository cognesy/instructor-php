# Technical Challenges

## 1. Default Driver Fallback and Compiler Resolution

**Problem:** If no `UseLlmConfig` capability is installed, `build()` still needs to produce a working AgentLoop with a default driver. Additionally, the context compiler (which may have been set or wrapped by capabilities) must be applied to whichever driver is used.

**Solution:** `build()` follows a two-step resolution:

1. Resolve the final compiler (capability-set or default)
2. Apply it to the driver (capability-set or default)

```php
public function build(): AgentLoop
{
    $compiler = $this->contextCompiler ?? new ConversationWithCurrentToolTrace();
    $driver = $this->resolveDriver($compiler);
    // ... resolve tool factories, snapshot hook stack, wire executor
}

private function resolveDriver(CanCompileMessages $compiler): CanUseTools
{
    if ($this->driver !== null) {
        return match (true) {
            $this->driver instanceof CanAcceptMessageCompiler
                => $this->driver->withMessageCompiler($compiler),
            default => $this->driver,
        };
    }

    return new ToolCallingDriver(
        llm: LLMProvider::new(),
        messageCompiler: $compiler,
        events: $this->events,
    );
}
```

This ensures:
- `UseLlmConfig` can set the driver without knowing about the compiler
- Capabilities can set/wrap the compiler without knowing about the driver
- Both resolve cleanly during `build()` regardless of installation order
- The zero-config path (`AgentBuilder::base()->build()`) uses default compiler + default driver


## 2. UseLlmConfig Needs Access to EventHandler

**Problem:** `UseLlmConfig` constructs a `ToolCallingDriver` which needs the event handler. During `install()`, the event handler is available via `$builder->eventHandler()`.

**Current code:**
```php
$driver = new ToolCallingDriver(
    llm: $llmProvider,
    messageCompiler: $compiler,
    retryPolicy: $retryPolicy,
    events: $builder->eventHandler(),  // available during install()
);
$builder->withDriver($driver);
```

**Risk:** If `withEvents()` is called AFTER `UseLlmConfig` is installed, the driver holds a stale event handler reference.

**Solution:** Document ordering constraint: call `withEvents()` before installing capabilities (same as current behavior — the existing codebase already has this comment). Alternatively, `UseLlmConfig` could use a tool factory pattern to defer driver creation until `build()`, but this adds complexity for an edge case. The ordering constraint is simpler and already established.


## 3. Capability Installation Order

**Problem:** Some capabilities may depend on others. For example, `UseSubagents` uses a tool factory that receives the resolved driver. If `UseLlmConfig` hasn't been installed yet, the factory gets the default driver.

**Current behavior:** Tool factories execute during `build()`, after all capabilities have been installed. The driver is resolved first, then factories run. This means capability installation order doesn't matter for tool factories — they always get the final driver.

**Remaining concern:** Hook priority ordering. Currently guards run at priority 200, context config at 100, capability hooks at 0 or custom, and post-processing hooks at negative priorities. This ordering is established by each capability independently. No cross-capability dependency exists — each declares its own priority.

**Solution:** No change needed. The existing priority system handles ordering. Document the priority convention:

```
200+    Guards (execution limits)
100     Context preparation (system prompt, response format)
0       Default (most capability hooks)
-50     Persistence (task persistence)
-100    Post-processing
-200    Deferred processing (summarization, finish reason)
-210    Secondary deferred (buffer summarization)
```


## 4. Guard Defaults — What Happens Without UseGuards

**Problem:** Currently AgentBuilder always adds guard hooks with defaults (20 steps, 32768 tokens, 300s). After refactoring, an agent built without `UseGuards` has NO guards. An agent with a broken tool could loop indefinitely.

**Options:**

a) **Accept it.** A guardless agent is a valid configuration. Simple Q&A agents don't need guards. If you forget guards on a complex agent, that's a developer error — same as forgetting to set a timeout on an HTTP client.

b) **AgentBuilder::standard()** factory that includes `UseGuards` with defaults:
```php
public static function standard(): self
{
    return self::base()
        ->withCapability(new UseGuards());  // defaults: 20 steps, 32768 tokens, 300s
}
```

c) **Warn at build time** if no guard hooks are registered. Not a blocker — just a notice.

**Recommendation:** Option (b). Provide `base()` (truly bare) and `standard()` (with default guards). Users who want `base()` know what they're doing. Users who want reasonable defaults use `standard()`. This makes the "missing guards" scenario intentional rather than accidental.

```php
// Bare — no opinions
$loop = AgentBuilder::base()->build();

// Standard — sensible defaults
$loop = AgentBuilder::standard()
    ->withCapability(new UseBash())
    ->build();
```


## 5. Migration Path — Existing Call Sites

**Problem:** All current AgentBuilder usage calls `withMaxSteps()`, `withSystemPrompt()`, etc. These methods are being removed.

**Impact scan (examples + tests):**
- `examples/D01_Agents/AgentLoopBashTool/run.php` — uses `withMaxSteps(5)`
- `examples/D02_AgentBuilder/AgentBasic/run.php` — uses `withLlmPreset()`
- `examples/D02_AgentBuilder/AgentHooks/run.php` — uses `withMaxSteps(5)`
- All AgentBuilder tests that use fluent config methods
- Any internal code using `withSystemPrompt()`, `withMaxTokens()`, etc.

**Migration strategy:**

Phase 1: Create the new capabilities (`UseGuards`, `UseContextConfig`, `UseLlmConfig`).

Phase 2: Add `standard()` factory.

Phase 3: Deprecate (or remove, if acceptable) the old fluent methods on AgentBuilder. Each method maps 1:1 to a capability:
```
->withMaxSteps(20)           →  ->withCapability(new UseGuards(maxSteps: 20))
->withMaxTokens(32768)       →  ->withCapability(new UseGuards(maxTokens: 32768))
->withTimeout(300)           →  ->withCapability(new UseGuards(timeout: 300))
->withSystemPrompt($prompt)  →  ->withCapability(new UseContextConfig(systemPrompt: $prompt))
->withResponseFormat($fmt)   →  ->withCapability(new UseContextConfig(responseFormat: $fmt))
->withLlmPreset('anthropic') →  ->withCapability(new UseLlmConfig(preset: 'anthropic'))
->withMaxRetries(3)          →  ->withCapability(new UseLlmConfig(maxRetries: 3))
->withContextCompiler($c)    →  stays as ->withContextCompiler($c) (core primitive)
->withFinishReasons($r)      →  ->withCapability(new UseGuards(finishReasons: $r))
```

Phase 4: Remove old methods + private build helpers (`addGuardHooks()`, `addContextHooks()`, `addMessageHooks()`). Remove associated properties.

**Estimated scope:** ~15-20 call sites across examples, tests, and internal code. Each is a mechanical transformation.


## 6. UseGuards Composability — Multiple Installations

**Problem:** What if a user installs `UseGuards` twice with different values?

```php
AgentBuilder::base()
    ->withCapability(new UseGuards(maxSteps: 20))
    ->withCapability(new UseGuards(maxSteps: 50))  // oops?
    ->build();
```

Both hook sets get registered. The agent would have TWO `StepsLimitHook` instances — the stricter one (20) would fire first and dominate.

**Options:**

a) **Accept it.** The stricter guard wins. This is how hook composition works — no special casing needed. Document it.

b) **Named hook replacement.** If a hook is registered with the same `$name` as an existing one, it replaces rather than duplicates. This would require `HookStack` to support dedup by name.

c) **Capability dedup.** AgentBuilder tracks installed capability types and warns/replaces on duplicate.

**Recommendation:** Option (a) for now. The stricter guard wins naturally. If this becomes a real user confusion point, option (b) is the clean fix — hook names already exist in the system.


## 7. FinishReasons — Where Does It Live?

**Problem:** `FinishReasonHook` is currently added by `addMessageHooks()` in AgentBuilder. It's conceptually a guard (stops execution based on a condition), but it's triggered differently (afterStep, checking inference response).

**Options:**

a) Include in `UseGuards` with an optional `finishReasons` parameter.
b) Separate `UseFinishReasons` capability.

**Recommendation:** Option (a). Finish reasons are a stop condition, which is what guards do. Having a separate capability for one hook is over-granular. `UseGuards` becomes the "when to stop" capability:

```php
new UseGuards(
    maxSteps: 20,
    maxTokens: 32768,
    timeout: 300,
    finishReasons: [InferenceFinishReason::EndTurn],
)
```


## 8. Testing Capabilities in Isolation

**Problem:** Capabilities call `$builder->addHook()` and `$builder->withTools()`. Testing a capability requires an AgentBuilder instance.

**Solution:** This is already fine. Capabilities are tested by:
1. Creating `AgentBuilder::base()`
2. Installing the capability
3. Building and inspecting the resulting AgentLoop (tools, hooks)
4. Or: running the AgentLoop against test state and asserting behavior

The capability's `install()` is pure side-effects on the builder — easy to test. The hooks themselves are independently testable via `HookContext`.


## 9. Composite Capabilities and Double-Installation

**Problem:** A composite capability like `UseCodingAgent` installs `UseGuards` internally. If the user also installs `UseGuards` explicitly, hooks are duplicated (see challenge #6).

```php
AgentBuilder::base()
    ->withCapability(new UseCodingAgent($workDir))   // installs UseGuards(30, ...)
    ->withCapability(new UseGuards(maxSteps: 10))     // user override
    ->build();
```

**Solution:** Same as #6 — the stricter guard wins. If precise override is needed, the composite capability should accept guard configuration:

```php
new UseCodingAgent($workDir, guards: new UseGuards(maxSteps: 10))
```

This is a capability design concern, not a framework concern.


## 10. Context Compiler Wrapping Order

**Problem:** The context compiler is a core builder primitive that capabilities can read and wrap:

```php
// Capability A wraps the compiler
$inner = $builder->contextCompiler() ?? new ConversationWithCurrentToolTrace();
$builder->withContextCompiler(new TokenBudgetCompiler($inner));

// Capability B wraps it again
$inner = $builder->contextCompiler(); // gets TokenBudgetCompiler
$builder->withContextCompiler(new RetrievalAugmentedCompiler($inner));
```

The wrapping order depends on capability installation order. Capability installed last wraps outermost (executes first during compilation).

**Risk:** If two capabilities both wrap the compiler, the outer wrapper runs first. This is usually fine — wrappers are independent transformations. But if a wrapper depends on seeing the output of another wrapper, ordering matters.

**Solution:** Accept installation-order-dependent wrapping. This is the same model as middleware stacks in HTTP frameworks — last added wraps outermost. The pattern is well-understood.

For the common case — a single capability setting a custom compiler — no wrapping happens and order doesn't matter. For rare multi-wrapper scenarios, document that installation order = wrapping order (innermost first, outermost last).

If ordering becomes a real problem, the upgrade path is Option B from the design discussion: a `wrapContextCompiler(callable $decorator)` method that collects decorators and applies them in priority order during `build()`. But this is premature until a real ordering conflict surfaces.

**Note:** The compiler getter (`contextCompiler()`) returning `null` when nothing has been set is intentional. It lets the first capability decide whether to wrap the default or replace it entirely:

```php
// Wrapping: preserve existing (or default)
$inner = $builder->contextCompiler() ?? new ConversationWithCurrentToolTrace();
$builder->withContextCompiler(new MyWrapper($inner));

// Replacing: ignore existing
$builder->withContextCompiler(new MyCustomCompiler());
```


## Summary of Changes

| Area | Action | Risk |
|---|---|---|
| New `UseGuards` capability | Create | Low — mechanical extraction |
| New `UseContextConfig` capability | Create | Low — mechanical extraction |
| New `UseLlmConfig` capability | Create | Low — mechanical extraction |
| `AgentBuilder::standard()` factory | Add | None — additive |
| Context compiler as core primitive | Keep/refine | Low — already exists, just stays |
| `build()` compiler→driver resolution | Refactor | Low — clean two-step resolution |
| Remove fluent config methods | Delete | Medium — call site migration |
| Remove private build helpers | Delete | Low — internal only |
| Remove 10+ properties from AgentBuilder | Delete | Low — moved to capabilities |
| Update examples | Edit | Low — mechanical |
| Update tests | Edit | Medium — may need new test patterns |
