# P1: Facade Trait Sprawl

## Problem Statement

The `StructuredOutput` facade class uses 7 traits to compose its functionality:

```php
class StructuredOutput
{
    use HandlesEvents;               // from Events package
    use Traits\HandlesLLMProvider;
    use Traits\HandlesExecutionBuilder;
    use Traits\HandlesRequestBuilder;
    use Traits\HandlesConfigBuilder;
    use Traits\HandlesPartialUpdates;
    use Traits\HandlesSequenceUpdates;
    // Plus 6 local properties and ~200 lines of code
}
```

This creates a "trait soup" where behavior is scattered across files.

## Evidence

### 1. Method Distribution

| Trait/Source | Methods | Lines |
|--------------|---------|-------|
| `HandlesEvents` | 3 | ~20 |
| `HandlesLLMProvider` | 5 | ~50 |
| `HandlesExecutionBuilder` | 3 | ~30 |
| `HandlesRequestBuilder` | 15+ | ~100 |
| `HandlesConfigBuilder` | 12 | ~80 |
| `HandlesPartialUpdates` | 4 | ~30 |
| `HandlesSequenceUpdates` | 2 | ~15 |
| `StructuredOutput` (direct) | 15 | ~150 |
| **Total** | **~60** | **~475** |

### 2. Overlapping Responsibilities

Multiple traits handle configuration-adjacent concerns:

```php
// HandlesConfigBuilder.php
public function withMaxRetries(int $maxRetries) : self
public function withOutputMode(OutputMode $outputMode): static
public function withToolName(string $toolName): static

// HandlesRequestBuilder.php
public function withModel(string $model): static
public function withOptions(array $options): static
public function withMessages(...): static

// StructuredOutput.php (direct)
public function with(
    $messages, $responseModel, $system, $prompt, $examples,
    $model, $maxRetries, $options, $toolName, $toolDescription,
    $retryPrompt, $mode,
) : static  // Duplicates trait methods!
```

### 3. Hidden Dependencies Between Traits

Traits assume presence of properties from other traits:

```php
// HandlesConfigBuilder.php
trait HandlesConfigBuilder {
    private StructuredOutputConfigBuilder $configBuilder;  // Must exist
    // ...
}

// HandlesRequestBuilder.php
trait HandlesRequestBuilder {
    private StructuredOutputRequestBuilder $requestBuilder;  // Must exist
    // ...
}
```

These hidden dependencies aren't enforced by PHP and can break silently.

### 4. Navigation Difficulty

To understand what `StructuredOutput` does, you need to read 8 files:

```
StructuredOutput.php
├── Traits/HandlesLLMProvider.php
├── Traits/HandlesExecutionBuilder.php
├── Traits/HandlesRequestBuilder.php
├── Traits/HandlesConfigBuilder.php
├── Traits/HandlesPartialUpdates.php
├── Traits/HandlesSequenceUpdates.php
└── Events/Traits/HandlesEvents.php (external)
```

## Impact

- **Code navigation** - Hard to find where a method is defined
- **Method conflicts** - Risk of trait method collisions
- **Testing** - Can't test traits in isolation (they depend on class state)
- **IDE support** - Some IDEs struggle with trait composition
- **Onboarding** - New developers confused by trait soup

## Root Cause

Traits were used to "organize" code by concern, but the concerns are too interrelated to truly separate. The result is artificial separation that adds complexity without benefit.

## Proposed Solution

### Option A: Inline All Traits (Quick Win)

Merge all traits into `StructuredOutput.php`:

**Before**: 8 files, ~475 LOC total
**After**: 1 file, ~300 LOC (with deduplication)

Benefits:
- All code in one place
- No hidden dependencies
- Easy to navigate
- Can be done incrementally

### Option B: Extract Builder Classes

Keep facade thin, delegate to explicit builders:

```php
class StructuredOutput
{
    private RequestBuilder $request;
    private ConfigBuilder $config;
    private OutputBuilder $output;

    public function withMessages(...$messages): static {
        $this->request->withMessages(...$messages);
        return $this;
    }

    public function create(): PendingStructuredOutput {
        return $this->output->create(
            $this->request->build(),
            $this->config->build(),
        );
    }
}
```

Benefits:
- Explicit dependencies
- Testable builders
- Clear delegation

### Option C: Consolidate Related Traits (Medium)

Merge related traits into 2-3:

```php
class StructuredOutput
{
    use HandlesEvents;              // Keep (from external package)
    use HandlesRequestBuilding;     // Merge: LLMProvider + RequestBuilder + PartialUpdates + SequenceUpdates
    use HandlesConfiguration;       // Merge: ConfigBuilder + ExecutionBuilder
}
```

## Recommended: Option A

Inline traits because:
1. **Lowest risk** - No behavioral changes
2. **Immediate clarity** - Single file to understand
3. **Enables further refactoring** - Easier to identify duplication once inline
4. **Quick execution** - Can be done in <2 hours

## File Changes

### Delete

```
Traits/HandlesLLMProvider.php
Traits/HandlesExecutionBuilder.php
Traits/HandlesRequestBuilder.php
Traits/HandlesConfigBuilder.php
Traits/HandlesPartialUpdates.php
Traits/HandlesSequenceUpdates.php
```

### Modify

```
StructuredOutput.php  // Inline all trait code
```

## Migration Steps

1. Copy trait methods into `StructuredOutput.php`
2. Copy trait properties into class properties
3. Remove `use` statements
4. Delete trait files
5. Run tests
6. Identify and remove duplicate methods (e.g., `with()` vs individual setters)

## Risk Assessment

- **Very low risk** - No behavioral changes, just file reorganization
- **Easy to verify** - Tests should pass unchanged
- **Reversible** - Can extract traits again if needed

## Estimated Effort

- Option A: 2 hours
- Option B: 8 hours
- Option C: 4 hours

## Success Metrics

- Single file for `StructuredOutput` facade
- Remove 6 trait files
- No change in public API
- All tests pass
