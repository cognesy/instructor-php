# Comprehensive Error Handling Study

**Date:** 2026-01-02
**Feature:** v1.5 Enhanced Error Feedback
**Status:** Planning Phase (Revised)
**Effort:** 3-4 weeks

---

## Critical Insight

This study originally focused only on validation errors. After analysis, we discovered:

**TWO SEPARATE ERROR PATHS EXIST:**

| Layer | Error Type | Current State | Impact |
|-------|-----------|---------------|--------|
| **Inference (Polyglot)** | Tool calling, malformed JSON, content filter | ❌ THROWS EXCEPTION, NO RETRY | **MAJOR** |
| **StructuredOutput (Instructor)** | Schema validation, type mismatches | ✅ Has retry (no feedback) | Medium |

**The tool calling error path is a MAJOR gap - it throws immediately with no retry and no feedback.**

---

## The Two Error Paths

### Path 1: Inference Errors (MAJOR GAP)

**Location:** `packages/polyglot/src/Inference/PendingInference.php:111-133`

```php
public function response() : InferenceResponse {
    if ($response->hasFinishedWithFailure()) {
        throw new RuntimeException('Inference failed: ' . $response->finishReason());
        //    ↑ malformed_function_call, content_filter, length, etc.
        //    ↑ THROWS IMMEDIATELY - NO RETRY, NO FEEDBACK
    }
}
```

**Error types:**
- `malformed_function_call` - LLM generates invalid JSON in tool arguments
- `content_filter` - Safety filter triggered
- `length` - Max tokens exceeded
- HTTP errors, timeouts, rate limits

**Current behavior:** Exception thrown → User sees error → No recovery

### Path 2: Validation Errors (Partial Solution)

**Location:** `packages/instructor/src/Core/AttemptIterator.php:85-131`

```php
private function finalizeAttempt(...) {
    $validationResult = $this->responseGenerator->makeResponse(...);

    if ($validationResult->isFailure()) {
        $failed = $this->retryPolicy->recordFailure(...);
        if ($this->retryPolicy->shouldRetry($failed, $validationResult)) {
            return $this->retryPolicy->prepareRetry($failed);
            //     ↑ Currently returns unchanged - NO feedback to LLM!
        }
    }
}
```

**Error types:**
- Missing required fields
- Wrong data types
- Constraint violations (min/max, patterns, enums)
- Custom validation rules

**Current behavior:** Retry exists but NO error feedback sent to LLM

---

## Comprehensive Solution

See: [implementation-plan.md](./implementation-plan.md) for full details.

### Key Components

```
┌─────────────────────────────────────────────────────────────────┐
│                      StructuredOutput                            │
│  withErrorFormatter()  |  withInferenceRetries()  |  withMaxRetries()
└─────────────────────────────────────────────────────────────────┘
                              │
         ┌────────────────────┴────────────────────┐
         ▼                                         ▼
┌─────────────────────┐                ┌─────────────────────┐
│ DefaultRetryPolicy  │                │ InferenceErrorHandler│
│                     │                │                     │
│ Handles: Validation │                │ Handles: Tool calls │
│ Location: Instructor│                │ Location: Generators│
└─────────────────────┘                └─────────────────────┘
         │                                         │
         └────────────────────┬────────────────────┘
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    CanFormatErrorFeedback                        │
│                                                                  │
│  UnifiedErrorFormatter         ProviderAwareErrorFormatter       │
│  (handles all error types)     (OpenAI/Claude/Gemini optimized)  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        ErrorContext                              │
│                                                                  │
│  category: ErrorCategory (Validation | MalformedToolCall | ...)  │
│  errors: array                                                   │
│  execution: StructuredOutputExecution                            │
│  isRetryable(): bool                                             │
│  provider(): string                                              │
└─────────────────────────────────────────────────────────────────┘
```

### Error Categories

```php
enum ErrorCategory: string
{
    // Validation layer
    case Validation = 'validation';

    // Inference layer
    case MalformedToolCall = 'malformed_tool_call';  // ← MAJOR
    case ContentFilter = 'content_filter';
    case MaxTokens = 'max_tokens';
    case RateLimit = 'rate_limit';
    case Timeout = 'timeout';
    case InferenceUnknown = 'inference_unknown';

    public function isRetryable(): bool {
        return match($this) {
            self::Validation => true,
            self::MalformedToolCall => true,  // LLM CAN fix its JSON
            self::MaxTokens => true,
            self::RateLimit => true,
            self::ContentFilter => false,     // Usually not fixable
            self::InferenceUnknown => false,
        };
    }
}
```

---

## Expected Outcomes

### Before

| Error Type | Retry | Feedback | Recovery Rate |
|------------|-------|----------|---------------|
| Validation errors | ✅ | ❌ | ~60-70% |
| Tool calling errors | ❌ | ❌ | 0% (throws) |

### After

| Error Type | Retry | Feedback | Recovery Rate |
|------------|-------|----------|---------------|
| Validation errors | ✅ | ✅ | ~80-90% |
| Tool calling errors | ✅ | ✅ | ~70-80% |

---

## Usage (Proposed API)

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\ErrorFormatters\ProviderAwareErrorFormatter;

$user = (new StructuredOutput)
    ->withResponseClass(User::class)
    ->withErrorFormatter(new ProviderAwareErrorFormatter())
    ->withInferenceRetries(2)   // ← NEW: Retry tool calling errors
    ->withMaxRetries(3)          // Existing: Retry validation errors
    ->get();
```

---

## Implementation Phases

### Phase 1: Foundation (Week 1)
- ErrorContext value object
- ErrorCategory enum
- CanFormatErrorFeedback interface
- ProviderDetector utility
- UnifiedErrorFormatter
- ProviderAwareErrorFormatter

### Phase 2: Inference Error Handling (Week 2)
- **InferenceErrorHandler** (NEW - the major gap)
- Wrap inference calls in all generators
- Integration tests

### Phase 3: Testing & Documentation (Week 3)
- Comprehensive tests for both error paths
- Provider-specific formatting tests
- Documentation and examples

---

## Files

- **README.md** (this file) - Overview and rationale
- **implementation-plan.md** - Complete technical specification

---

## Key Takeaways

1. **Tool calling errors are a MAJOR gap** - They throw immediately with no recovery
2. **Two separate architectural layers** - Need unified approach
3. **Error feedback improves recovery** - LLMs can self-correct when told what went wrong
4. **Provider-specific formatting helps** - Different LLMs respond to different formats
5. **Backward compatible** - All changes are opt-in via new methods

---

## References

**Current code paths:**
- Inference errors: `packages/polyglot/src/Inference/PendingInference.php:111-133`
- Validation errors: `packages/instructor/src/Core/AttemptIterator.php:85-131`
- Retry policy: `packages/instructor/src/RetryPolicy/DefaultRetryPolicy.php`
- Generators: `packages/instructor/src/ResponseIterators/*/`

**Finish reasons:** `packages/polyglot/src/Inference/Enums/InferenceFinishReason.php`
- `malformed_function_call` → `Error`
- `content_filter` → `ContentFilter`
- `length` → `Length`
