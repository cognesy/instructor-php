# Review 2: Code Changes After Corrections

Follow-up to `better-context-review-1.md`. Reviews the codebase after the corrected plan was applied.

---

## What Changed Since Last Review

Compared to the intermediate state reviewed informally, this batch addresses several issues raised in review 1:

### Addressed

1. **`messagesForInference()` deleted** (not just commented out) — AgentContext no longer contains any compilation logic.

2. **`withStepOutputRouted()` moved from AgentContext to AgentState** — AgentContext no longer contains routing policy. The routing logic is now in three private methods on AgentState: `withRoutedStepOutput()`, `withOutputBuffered()`, `withFinalResponseAppended()`.

3. **`CanCompileMessages` moved to `Core\Contracts`** — The foundation contract now lives at `Cognesy\Agents\Core\Contracts\CanCompileMessages`, alongside `CanAcceptMessageCompiler`. Dependency direction is correct: `Core\Contracts` owns the interface, `Context\Compilers` provides implementations.

4. **Section constants moved to `ContextSections`** — New dedicated class at `Context\ContextSections` with constants `DEFAULT`, `BUFFER`, `SUMMARY`, `EXECUTION_BUFFER` plus `inferenceOrder()` factory. AgentContext no longer owns these.

5. **`SelectedSections::default()` factory added** — Eliminates duplication. Drivers call `SelectedSections::default()` instead of repeating the 4-section list. Uses `ContextSections::inferenceOrder()`.

6. **`toInferenceContext()` renamed to `toCachedContext()`** — Clearer name for what it does (prompt caching setup, not message compilation).

7. **Old `Context\CanCompileMessages` deleted** — No stale interface lingering.

8. **Tests updated** — Routing tests now use `ContextSections::` constants, reference `AgentState::withCurrentStep()` as the entry point (not AgentContext).

### Net Result

`AgentContext` is now a pure data object with:
- Accessors: `store()`, `metadata()`, `systemPrompt()`, `responseFormat()`, `messages()`
- Mutators: `with()`, `withMessageStore()`, `withMessages()`, `withMetadataKey()`, `withMetadata()`, `withSystemPrompt()`, `withResponseFormat()`
- Serialization: `toArray()`, `fromArray()`
- One remaining helper: `toCachedContext()` (prompt caching VO construction)

No policy methods. No section constants. No routing. This matches the plan's goal.

---

## Assessment: What's Good

### 1. AgentContext is Clean
Zero policy logic. The class went from 197 lines to 143, and everything removed was behavior (routing, compilation, section constants). What remains is pure data + serialization. This is exactly what was promised.

### 2. Routing Moved to the Right Place (For Now)
Moving `withStepOutputRouted()` to private methods on `AgentState` is a practical intermediate step. The routing logic stays close to the state transition that triggers it (`withCurrentStep()`), and it's no longer on the data object that represents context. The routing is still hardcoded, but it's in a location where it's easier to extract later.

### 3. ContextSections is Well-Designed
```php
final class ContextSections {
    public const string DEFAULT = 'messages';
    public const string BUFFER = 'buffer';
    public const string SUMMARY = 'summary';
    public const string EXECUTION_BUFFER = 'execution_buffer';

    public static function inferenceOrder(): array { ... }
}
```
Constants + factory method. Simple, discoverable, no magic. `inferenceOrder()` codifies what was previously implicit knowledge (the section compilation order).

### 4. Namespace Structure is Correct
```
Core\Contracts\CanCompileMessages        ← foundation contract
Core\Contracts\CanAcceptMessageCompiler  ← injection mechanism
Context\Compilers\SelectedSections       ← implementation
Context\Compilers\AllSections            ← implementation
Context\ContextSections                  ← section name constants
Context\AgentContext                     ← pure data
```
Dependencies flow downward: `Core\Contracts` → nothing; `Context\Compilers` → `Core\Contracts`; Drivers → `Core\Contracts`. Clean.

### 5. No References to Old Methods Remain
Grep confirms: zero references to `messagesForInference`, `withStepOutputRouted`, or `AgentContext::DEFAULT_SECTION` etc. in `src/`. The migration is complete within the source tree.

### 6. All Tests Pass
316 tests, clean. The routing tests properly test through `AgentState::withCurrentStep()`, which is the real entry point.

---

## Issues Found

### 1. Routing is Still Hardcoded on AgentState

`AgentState` lines 473-493 contain three private methods that ARE the routing policy:

```php
private function withRoutedStepOutput(AgentStep $step): AgentContext { ... }
private function withOutputBuffered(Messages $output): AgentContext { ... }
private function withFinalResponseAppended(Messages $output): AgentContext { ... }
```

This is better than having it on AgentContext, but it means `AgentState` now mixes two concerns:
- State management (its primary job)
- Routing policy (moved here from AgentContext)

The plan calls for an injected `CanRouteStepOutput`. Right now, swapping routing behavior requires subclassing or replacing `AgentState`, which defeats the purpose of immutable value objects.

**Status:** Known intermediate state, not a blocker. Track for next batch.

### 2. Summarization Hooks Still Reference Section Names Directly

`MoveMessagesToBufferHook` (line 48-52):
```php
$newMessageStore = $state->store()
    ->section($this->bufferSection)
    ->appendMessages($overflow)
    ->section('messages')
    ->setMessages($keep);
```

`SummarizeBufferHook` (line 70-73):
```php
$newStore = $state->store()
    ->section($this->bufferSection)->setMessages(Messages::empty())
    ->section($this->summarySection)->setMessages(Messages::fromString($summary));
```

These hooks use hardcoded string `'messages'` and injected section names from `SummarizationPolicy`. They work because the section names haven't changed, but they bypass `ContextSections` constants. They should reference `ContextSections::DEFAULT`, `ContextSections::BUFFER`, etc.

**Risk:** Low (they work), but inconsistent with the rest of the codebase.

### 3. `toCachedContext()` Still on AgentContext

This method builds a `CachedInferenceContext` from the system prompt, response format, and tool schemas. It's not exactly "policy," but it IS an inference-specific concern — it decides how to structure the prompt cache.

The plan says AgentContext should hold "only data accessors, with*() mutators, and serialization." `toCachedContext()` is a VO construction helper, which arguably fits, but it creates coupling between AgentContext and `CachedInferenceContext`/`ResponseFormat` from the Polyglot package.

**Judgment:** Acceptable for now. If AgentContext needs to be a truly package-independent data object later, this should move to the driver or a dedicated factory.

### 4. `AgentState::forNextExecution()` Still Directly References ContextSections

Line 122:
```php
$store = $this->context->store()->section(ContextSections::EXECUTION_BUFFER)->clear();
```

This is execution lifecycle logic that knows about the section structure. When routing is extracted to `CanRouteStepOutput`, this "reset" behavior should live with it — the router that populates the execution buffer should also know how to clear it.

### 5. `AgentState::withUserMessage()` Uses ContextSections::DEFAULT Directly

Line 186-189:
```php
$store = $this->context->store()
    ->section(ContextSections::DEFAULT)
    ->appendMessages($userMessage);
```

`AgentState` has convenience methods that reach into sections by name. This is fine for the DEFAULT section (it's the primary interaction log), but it means `AgentState` knows about the internal section structure of the message store.

**Judgment:** Pragmatic. `withUserMessage()` is a convenience API that users will call. Requiring them to know about sections for something this basic would be over-engineering.

---

## Recommended Next Steps

### Priority 1: Extract Routing to Injectable Contract

This is the last piece of hardcoded policy. The change is well-scoped:

1. **Define the contract** (already in the plan):
```php
interface CanRouteStepOutput {
    public function route(AgentContext $context, AgentStep $step): AgentContext;
    public function resetForNextExecution(AgentContext $context): AgentContext;
}
```

2. **Move the three private methods** from AgentState into `DefaultStepOutputRouter implements CanRouteStepOutput`.

3. **Inject the router into AgentState** or (better) into `AgentLoop` which calls `$state->withCurrentStep()`. The router transforms the context, and `AgentState` receives the result.

4. **Wire through AgentBuilder** — `withContextRouter(CanRouteStepOutput $router)`, same pattern as the compiler.

This completes the "AgentContext has zero policy logic" AND "AgentState doesn't carry policy" goals.

### Priority 2: Update Summarization Hooks to Use ContextSections

Small, safe change:
- `MoveMessagesToBufferHook`: Replace `'messages'` string literal with `ContextSections::DEFAULT`
- `SummarizationPolicy`: Default section values should reference `ContextSections::BUFFER` and `ContextSections::SUMMARY`

This aligns the existing capability with the new constants.

### Priority 3: Plan the Compaction Pipeline

The compiler seam and routing extraction lay the groundwork. Next is the `BudgetAwareMessageCompiler` profile which needs:

1. **`ContextTokenBudget` value object** — Already designed in the plan
2. **`CanEstimateTokens` implementation** — Use the existing `Tokenizer::tokenCount()` from the summarization utils
3. **One concrete `CanTransformContext` transform** — Start with `PruneLargeToolOutputsTransform` (cheapest, no LLM needed, highest impact per the research)
4. **`PipelineCompactor` wiring** — Compose transforms into `CanCompactMessages`

This is a new feature, not a refactor. Consider building it as a new `AgentCapability` (like `UseSummarization`) so it's opt-in.

### Priority 4: Migrate or Retire Summarization Hooks

The existing `MoveMessagesToBufferHook` + `SummarizeBufferHook` system overlaps with what the compaction pipeline will do. Two options:

**Option A: Keep as legacy, build compaction separately.** The hooks work via the hook system; compaction works via the compiler pipeline. They serve different use cases (hooks are reactive per-step; compaction is proactive per-inference).

**Option B: Rewrite as compaction transforms.** `MoveMessagesToBuffer` becomes a `CanTransformContext` that moves old messages out. `SummarizeBuffer` becomes the `IterativeSummaryUpdateTransform`. This unifies the two systems.

Recommendation: **Option A for now**, Option B when the compaction pipeline is proven. Don't break a working system to unify prematurely.

### Not Yet: Message Metadata

The plan assumes messages carry metadata (pinned, origin, compacted). This is the prerequisite for `PolicyDrivenMessageCompiler`, observation masking, and file-read deduplication. But it's not needed for the next two priorities (routing extraction and basic compaction), so defer it.

---

## Summary

The codebase is in a good state. AgentContext is genuinely pure data now — no policy, no constants, no routing. The compiler injection works correctly across all drivers. Tests are green.

The remaining policy on `AgentState` (routing) is the clear next target. After that, the architecture is ready for the compaction pipeline, which is the first real "new capability" this redesign enables.

**Scorecard:**

| Goal | Status |
|------|--------|
| AgentContext = pure data | Done |
| `messagesForInference()` removed | Done |
| `withStepOutputRouted()` removed from AgentContext | Done (moved to AgentState) |
| Section constants off AgentContext | Done (ContextSections) |
| CanCompileMessages in Core\Contracts | Done |
| Compiler injection into drivers | Done |
| `SelectedSections::default()` | Done |
| Routing extraction to CanRouteStepOutput | Next |
| Summarization hooks updated for ContextSections | Next |
| ContextTokenBudget + BudgetAwareCompiler | Future |
| Message metadata | Future |
| Compaction pipeline | Future |
