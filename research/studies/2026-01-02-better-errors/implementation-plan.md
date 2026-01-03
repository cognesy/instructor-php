# Enhanced Error Feedback Implementation Plan

**Date:** 2026-01-02
**Feature:** v1.5 Enhanced Error Feedback
**Effort:** 3-4 weeks (revised from 2 weeks)
**Risk:** MEDIUM (requires changes at two architectural layers)

---

## Executive Summary

This document describes a **comprehensive error handling strategy** that addresses two distinct error types in InstructorPHP:

1. **Validation Errors** (StructuredOutput layer) - Current partial solution exists
2. **Tool Calling Errors** (Inference layer) - **NO solution exists - MAJOR GAP**

The user correctly identified that focusing only on validation errors while ignoring tool calling errors is incomplete. This revised plan addresses both.

---

## Problem Analysis

### Two Error Types, Two Code Paths

```
┌─────────────────────────────────────────────────────────────────────┐
│                     USER REQUEST                                     │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│ INFERENCE LAYER (packages/polyglot)                                  │
│                                                                      │
│  PendingInference.response()                                         │
│       │                                                              │
│       ├─→ SUCCESS: Returns InferenceResponse                        │
│       │                                                              │
│       └─→ FAILURE: THROWS EXCEPTION ← ❌ NO RETRY, NO FEEDBACK       │
│                                                                      │
│  Error types:                                                        │
│  • malformed_function_call (invalid JSON in tool arguments)          │
│  • content_filter (safety filter triggered)                          │
│  • length (max tokens exceeded)                                      │
│  • HTTP errors, timeouts, rate limits                                │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼ (only if no exception)
┌─────────────────────────────────────────────────────────────────────┐
│ STRUCTURED OUTPUT LAYER (packages/instructor)                        │
│                                                                      │
│  ResponseGenerator.makeResponse()                                    │
│       │                                                              │
│       ├─→ SUCCESS: Result::success(value)                            │
│       │                                                              │
│       └─→ FAILURE: Result::failure(errors) ← ✅ HAS RETRY LOGIC      │
│                                                                      │
│  Error types:                                                        │
│  • Missing required fields                                           │
│  • Wrong data types                                                  │
│  • Constraint violations (min/max, patterns, enums)                  │
│  • Custom validation rules                                           │
└─────────────────────────────────────────────────────────────────────┘
```

### Current State: Validation Errors (PARTIAL SOLUTION EXISTS)

**Location:** `packages/instructor/src/Core/AttemptIterator.php:85-131`

```php
private function finalizeAttempt(StructuredOutputExecution $execution): StructuredOutputExecution {
    // ...
    $validationResult = $this->responseGenerator->makeResponse(...);

    if ($validationResult->isSuccess()) {
        return $execution->withSuccessfulAttempt(...);
    }

    // Failure - record and maybe retry
    $failed = $this->retryPolicy->recordFailure($execution, $validationResult, ...);

    if ($this->retryPolicy->shouldRetry($failed, $validationResult)) {
        return $this->retryPolicy->prepareRetry($failed);  // ← Error feedback could go here
    }

    $this->retryPolicy->finalizeOrThrow($failed, $validationResult);
}
```

**Current `prepareRetry()` in `DefaultRetryPolicy`:**
```php
public function prepareRetry(StructuredOutputExecution $execution): StructuredOutputExecution {
    // Default: no modifications for retry
    return $execution;  // ← NO ERROR FEEDBACK TO LLM!
}
```

**Problem:** Even though retry logic exists, NO error feedback is sent to the LLM.

---

### Current State: Tool Calling Errors (NO SOLUTION)

**Location:** `packages/instructor/src/ResponseIterators/Sync/SyncUpdateGenerator.php:50-69`

```php
public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
    // ...
    $inference = $this->inferenceProvider->getInference($execution)->response();
    //           ↑ THIS THROWS EXCEPTION IF malformed_function_call, etc.
    //           ↑ EXCEPTION BUBBLES UP UNHANDLED - NO RETRY!
    // ...
}
```

**Where the exception originates:** `packages/polyglot/src/Inference/PendingInference.php:111-133`

```php
public function response() : InferenceResponse {
    // ...
    try {
        $response = $this->makeResponse($this->execution->request());
    } catch (\Throwable $e) {
        $this->handleAttemptFailure($e);
        throw $e;  // ← THROWS TO CALLER
    }

    if ($response->hasFinishedWithFailure()) {
        $error = new \RuntimeException('Inference execution failed: ' . $response->finishReason()->value);
        throw $error;  // ← THROWS malformed_function_call, content_filter, length
    }
    // ...
}
```

**Problem:** Tool calling errors immediately throw exceptions that bubble up to the user. No retry, no error feedback.

---

## Comprehensive Solution Design

### Phase 1: Unified Error Handling Interface

Create a single interface that works for BOTH error types:

**New file:** `packages/instructor/src/Contracts/CanFormatErrorFeedback.php`

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Errors\ErrorContext;

/**
 * Formats errors for LLM feedback during retry attempts.
 *
 * Works with both:
 * - Validation errors (schema validation failures)
 * - Inference errors (tool calling failures, malformed JSON, etc.)
 *
 * DDD: This is a STRATEGY pattern for error formatting.
 */
interface CanFormatErrorFeedback
{
    /**
     * Format errors into LLM-consumable feedback message.
     *
     * @param ErrorContext $context Error context with type, errors, execution state
     * @return string Formatted error message for LLM
     */
    public function format(ErrorContext $context): string;

    /**
     * Identifier for this formatter (for debugging/logging)
     */
    public function name(): string;
}
```

**New file:** `packages/instructor/src/Errors/ErrorContext.php`

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Errors;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Errors\Enums\ErrorCategory;
use Throwable;

/**
 * Unified error context for both validation and inference errors.
 *
 * Value Object that encapsulates all error information for formatting.
 */
final readonly class ErrorContext
{
    public function __construct(
        public ErrorCategory $category,
        public array $errors,
        public StructuredOutputExecution $execution,
        public ?Throwable $exception = null,
        public ?string $rawContent = null,
    ) {}

    // FACTORY METHODS /////////////////////////////////////////////

    public static function fromValidationErrors(
        array $errors,
        StructuredOutputExecution $execution,
    ): self {
        return new self(
            category: ErrorCategory::Validation,
            errors: $errors,
            execution: $execution,
        );
    }

    public static function fromInferenceException(
        Throwable $exception,
        StructuredOutputExecution $execution,
        ?string $rawContent = null,
    ): self {
        return new self(
            category: self::categorizeInferenceError($exception),
            errors: [$exception->getMessage()],
            execution: $execution,
            exception: $exception,
            rawContent: $rawContent,
        );
    }

    // ACCESSORS //////////////////////////////////////////////////

    public function isValidationError(): bool {
        return $this->category === ErrorCategory::Validation;
    }

    public function isInferenceError(): bool {
        return $this->category->isInferenceError();
    }

    public function isRetryable(): bool {
        return $this->category->isRetryable();
    }

    public function attemptNumber(): int {
        return $this->execution->attemptCount();
    }

    public function modelName(): string {
        return $this->execution->request()->model();
    }

    public function provider(): string {
        return ProviderDetector::detect($this->modelName());
    }

    // INTERNAL ///////////////////////////////////////////////////

    private static function categorizeInferenceError(Throwable $exception): ErrorCategory {
        $message = strtolower($exception->getMessage());

        return match(true) {
            str_contains($message, 'malformed_function_call') => ErrorCategory::MalformedToolCall,
            str_contains($message, 'malformed') => ErrorCategory::MalformedToolCall,
            str_contains($message, 'invalid json') => ErrorCategory::MalformedToolCall,
            str_contains($message, 'content_filter') => ErrorCategory::ContentFilter,
            str_contains($message, 'safety') => ErrorCategory::ContentFilter,
            str_contains($message, 'length') => ErrorCategory::MaxTokens,
            str_contains($message, 'max_tokens') => ErrorCategory::MaxTokens,
            str_contains($message, 'rate_limit') => ErrorCategory::RateLimit,
            str_contains($message, 'timeout') => ErrorCategory::Timeout,
            default => ErrorCategory::InferenceUnknown,
        };
    }
}
```

**New file:** `packages/instructor/src/Errors/Enums/ErrorCategory.php`

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Errors\Enums;

enum ErrorCategory: string
{
    // Validation errors (StructuredOutput layer)
    case Validation = 'validation';

    // Inference errors (Polyglot layer)
    case MalformedToolCall = 'malformed_tool_call';
    case ContentFilter = 'content_filter';
    case MaxTokens = 'max_tokens';
    case RateLimit = 'rate_limit';
    case Timeout = 'timeout';
    case InferenceUnknown = 'inference_unknown';

    public function isInferenceError(): bool {
        return match($this) {
            self::Validation => false,
            default => true,
        };
    }

    public function isRetryable(): bool {
        return match($this) {
            self::Validation => true,
            self::MalformedToolCall => true,  // LLM can fix its JSON
            self::MaxTokens => true,          // Can retry with shorter prompt
            self::RateLimit => true,          // Retry after delay
            self::ContentFilter => false,     // Usually not retryable
            self::Timeout => true,            // Network issue, can retry
            self::InferenceUnknown => false,  // Unknown, don't retry
        };
    }

    public function label(): string {
        return match($this) {
            self::Validation => 'Validation Error',
            self::MalformedToolCall => 'Malformed Tool Call',
            self::ContentFilter => 'Content Filter',
            self::MaxTokens => 'Max Tokens Exceeded',
            self::RateLimit => 'Rate Limit',
            self::Timeout => 'Timeout',
            self::InferenceUnknown => 'Inference Error',
        };
    }
}
```

---

### Phase 2: Inference Error Handling (THE MAJOR GAP)

**Goal:** Catch inference exceptions and route them through retry logic.

**New file:** `packages/instructor/src/Core/InferenceErrorHandler.php`

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanFormatErrorFeedback;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Errors\ErrorContext;
use Cognesy\Instructor\Errors\Enums\ErrorCategory;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Messages\Messages;
use Throwable;

/**
 * Handles inference-level errors (tool calling, content filter, etc.)
 *
 * Converts exceptions into retryable states with error feedback.
 */
final readonly class InferenceErrorHandler
{
    public function __construct(
        private CanFormatErrorFeedback $errorFormatter,
        private int $maxInferenceRetries = 2,
    ) {}

    /**
     * Wrap inference call with error handling.
     *
     * @param StructuredOutputExecution $execution
     * @param callable $inferenceCallback Returns InferenceResponse
     * @return InferenceResponse|StructuredOutputExecution
     */
    public function handleInference(
        StructuredOutputExecution $execution,
        callable $inferenceCallback,
    ): InferenceResponse|StructuredOutputExecution {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxInferenceRetries) {
            try {
                return $inferenceCallback($execution);
            } catch (Throwable $e) {
                $lastException = $e;
                $errorContext = ErrorContext::fromInferenceException($e, $execution);

                // Don't retry non-retryable errors
                if (!$errorContext->isRetryable()) {
                    throw $e;
                }

                // Prepare retry with error feedback
                $execution = $this->prepareInferenceRetry($execution, $errorContext);
                $attempt++;
            }
        }

        // Max retries exceeded
        throw $lastException;
    }

    /**
     * Prepare execution for inference retry with error feedback.
     */
    private function prepareInferenceRetry(
        StructuredOutputExecution $execution,
        ErrorContext $errorContext,
    ): StructuredOutputExecution {
        $errorMessage = $this->errorFormatter->format($errorContext);

        $updatedRequest = $execution->request()->withMessages(
            $execution->request()->messages()->append(
                Messages::user($errorMessage)
            )
        );

        return $execution->withRequest($updatedRequest);
    }
}
```

**Update:** `packages/instructor/src/ResponseIterators/Sync/SyncUpdateGenerator.php`

```php
final readonly class SyncUpdateGenerator implements CanStreamStructuredOutputUpdates
{
    public function __construct(
        private InferenceProvider $inferenceProvider,
        private ?InferenceErrorHandler $errorHandler = null,  // ← NEW
    ) {
        $this->normalizer = new ResponseNormalizer();
    }

    public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
        $state = $execution->attemptState();
        if ($state !== null && !$state->hasMoreChunks()) {
            return $execution;
        }

        // Wrap inference call with error handling
        $result = $this->makeInferenceWithErrorHandling($execution);

        // If error handler returned updated execution (for retry), return it
        if ($result instanceof StructuredOutputExecution) {
            return $result;
        }

        // Otherwise, we have a successful inference response
        $inference = $result;
        $inference = $this->normalizer->normalizeContent($inference, $execution->outputMode());

        // ... rest of the method unchanged
    }

    private function makeInferenceWithErrorHandling(
        StructuredOutputExecution $execution
    ): InferenceResponse|StructuredOutputExecution {
        if ($this->errorHandler === null) {
            // No error handler - original behavior (throw exceptions)
            return $this->inferenceProvider->getInference($execution)->response();
        }

        return $this->errorHandler->handleInference(
            $execution,
            fn($exec) => $this->inferenceProvider->getInference($exec)->response()
        );
    }
}
```

---

### Phase 3: Error Formatters

Now that we have unified error context, formatters work for BOTH error types:

**New file:** `packages/instructor/src/ErrorFormatters/UnifiedErrorFormatter.php`

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\ErrorFormatters;

use Cognesy\Instructor\Contracts\CanFormatErrorFeedback;
use Cognesy\Instructor\Errors\ErrorContext;
use Cognesy\Instructor\Errors\Enums\ErrorCategory;

/**
 * Unified formatter that handles both validation and inference errors.
 */
final readonly class UnifiedErrorFormatter implements CanFormatErrorFeedback
{
    #[\Override]
    public function format(ErrorContext $context): string {
        return match(true) {
            $context->isValidationError() => $this->formatValidationError($context),
            $context->category === ErrorCategory::MalformedToolCall => $this->formatMalformedToolCall($context),
            $context->category === ErrorCategory::MaxTokens => $this->formatMaxTokensError($context),
            default => $this->formatGenericError($context),
        };
    }

    #[\Override]
    public function name(): string {
        return 'unified';
    }

    // ERROR TYPE FORMATTERS ///////////////////////////////////////

    private function formatValidationError(ErrorContext $context): string {
        $errors = array_map(fn($e) => "  - " . (string)$e, $context->errors);
        $errorList = implode("\n", $errors);
        $attempt = $context->attemptNumber();

        return <<<TEXT
Your previous response (attempt {$attempt}) had validation errors:

{$errorList}

Please review the JSON Schema and correct the following:
1. Ensure all required fields are present
2. Verify data types match the schema exactly
3. Check that values meet any constraints (min/max, patterns, enums, etc.)
4. Validate nested objects and arrays are properly structured

Generate the corrected structured output now.
TEXT;
    }

    private function formatMalformedToolCall(ErrorContext $context): string {
        $rawContent = $context->rawContent ?? 'not available';
        $attempt = $context->attemptNumber();

        return <<<TEXT
Your previous response (attempt {$attempt}) contained invalid JSON in the tool call arguments.

Error: {$context->errors[0]}

The tool call arguments could not be parsed as valid JSON.

Please ensure:
1. All JSON is properly formatted with matching braces and brackets
2. All strings are properly quoted with double quotes
3. No trailing commas in objects or arrays
4. All escape sequences are valid (use \\\\ for backslash, \\" for quotes)

Generate the tool call with valid JSON arguments.
TEXT;
    }

    private function formatMaxTokensError(ErrorContext $context): string {
        return <<<TEXT
Your previous response was truncated because it exceeded the maximum token limit.

Please provide a more concise response that fits within the token limit.
Focus on the essential data without unnecessary detail.

Generate a shorter structured output now.
TEXT;
    }

    private function formatGenericError(ErrorContext $context): string {
        $category = $context->category->label();
        $error = $context->errors[0] ?? 'Unknown error';

        return <<<TEXT
There was an error with your previous response.

Error type: {$category}
Details: {$error}

Please try again with a corrected response.
TEXT;
    }
}
```

**New file:** `packages/instructor/src/ErrorFormatters/ProviderAwareErrorFormatter.php`

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\ErrorFormatters;

use Cognesy\Instructor\Contracts\CanFormatErrorFeedback;
use Cognesy\Instructor\Errors\ErrorContext;
use Cognesy\Instructor\Errors\Enums\ErrorCategory;
use Cognesy\Instructor\Utils\ProviderDetector;

/**
 * Provider-specific error formatting for both validation and inference errors.
 *
 * Optimizes error messages for different LLM providers.
 */
final readonly class ProviderAwareErrorFormatter implements CanFormatErrorFeedback
{
    public function __construct(
        private ?CanFormatErrorFeedback $fallback = null,
    ) {}

    #[\Override]
    public function format(ErrorContext $context): string {
        $provider = $context->provider();

        return match($provider) {
            'openai' => $this->formatForOpenAI($context),
            'anthropic' => $this->formatForAnthropic($context),
            'google' => $this->formatForGoogle($context),
            default => $this->formatFallback($context),
        };
    }

    #[\Override]
    public function name(): string {
        return 'provider_aware';
    }

    // PROVIDER-SPECIFIC FORMATTERS ////////////////////////////////

    private function formatForOpenAI(ErrorContext $context): string {
        $category = $context->category->label();
        $errors = implode("\n- ", $context->errors);
        $guidance = $this->getGuidanceForCategory($context->category);

        return <<<TEXT
ERROR: {$category}

- {$errors}

ACTION REQUIRED: {$guidance}
TEXT;
    }

    private function formatForAnthropic(ErrorContext $context): string {
        $category = $context->category->label();
        $errors = implode("\n", $context->errors);
        $guidance = $this->getGuidanceForCategory($context->category);

        return <<<TEXT
<error type="{$context->category->value}">
{$category}:

{$errors}
</error>

<task>
{$guidance}
</task>
TEXT;
    }

    private function formatForGoogle(ErrorContext $context): string {
        $category = $context->category->label();
        $errors = implode("\n* ", $context->errors);
        $guidance = $this->getGuidanceForCategory($context->category);

        return <<<TEXT
**{$category}**

* {$errors}

**Required Action:** {$guidance}
TEXT;
    }

    private function formatFallback(ErrorContext $context): string {
        if ($this->fallback !== null) {
            return $this->fallback->format($context);
        }
        return (new UnifiedErrorFormatter())->format($context);
    }

    // GUIDANCE BY CATEGORY ////////////////////////////////////////

    private function getGuidanceForCategory(ErrorCategory $category): string {
        return match($category) {
            ErrorCategory::Validation =>
                "Review the JSON Schema and generate corrected output with all required fields and correct types.",
            ErrorCategory::MalformedToolCall =>
                "Generate valid JSON in the tool call arguments. Ensure proper formatting, quoting, and escaping.",
            ErrorCategory::MaxTokens =>
                "Provide a more concise response that fits within the token limit.",
            ErrorCategory::ContentFilter =>
                "Revise your response to comply with content policies.",
            ErrorCategory::RateLimit =>
                "Retry the request.",
            ErrorCategory::Timeout =>
                "Retry the request.",
            default =>
                "Try again with a corrected response.",
        };
    }
}
```

---

### Phase 4: Integration with Retry Policy

**Update:** `packages/instructor/src/RetryPolicy/DefaultRetryPolicy.php`

```php
final readonly class DefaultRetryPolicy implements CanDetermineRetry
{
    public function __construct(
        private CanHandleEvents $events,
        private ?CanFormatErrorFeedback $errorFormatter = null,  // ← NEW
    ) {}

    #[\Override]
    public function prepareRetry(
        StructuredOutputExecution $execution,
    ): StructuredOutputExecution {
        // If no formatter configured, maintain backward compatibility
        if ($this->errorFormatter === null) {
            return $execution;
        }

        // Format errors using configured formatter
        $errorContext = ErrorContext::fromValidationErrors(
            errors: $execution->currentErrors(),
            execution: $execution,
        );

        $errorMessage = $this->errorFormatter->format($errorContext);

        // Add error feedback as user message
        $updatedRequest = $execution->request()->withMessages(
            $execution->request()->messages()->append(
                Messages::user($errorMessage)
            )
        );

        return $execution->withRequest($updatedRequest);
    }

    // ... rest unchanged
}
```

---

### Phase 5: Fluent API Integration

**Update:** `packages/instructor/src/StructuredOutput.php`

```php
/**
 * Set error formatter for retry feedback.
 * Works for both validation errors and inference errors.
 */
public function withErrorFormatter(CanFormatErrorFeedback $formatter): self
{
    $this->errorFormatter = $formatter;
    return $this;
}

/**
 * Enable inference error retry with feedback.
 * Without this, inference errors (like malformed_function_call) throw immediately.
 */
public function withInferenceRetries(int $maxRetries = 2): self
{
    $this->maxInferenceRetries = $maxRetries;
    return $this;
}
```

**Usage:**

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\ErrorFormatters\ProviderAwareErrorFormatter;

$user = (new StructuredOutput)
    ->withResponseClass(User::class)
    ->withErrorFormatter(new ProviderAwareErrorFormatter())  // Format errors for LLM
    ->withInferenceRetries(2)                                 // Retry tool calling errors
    ->withMaxRetries(3)                                       // Retry validation errors
    ->get();
```

---

## Architecture Summary

```
┌─────────────────────────────────────────────────────────────────────┐
│                         StructuredOutput                             │
│                                                                      │
│  Configuration:                                                      │
│  • withErrorFormatter(CanFormatErrorFeedback)                        │
│  • withInferenceRetries(int)                                         │
│  • withMaxRetries(int)                                               │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         AttemptIterator                              │
│                                                                      │
│  Orchestrates retry loop for BOTH error types:                       │
│  • Validation errors → DefaultRetryPolicy.prepareRetry()             │
│  • Inference errors → InferenceErrorHandler.handleInference()        │
└─────────────────────────────────────────────────────────────────────┘
                                │
            ┌───────────────────┴───────────────────┐
            │                                       │
            ▼                                       ▼
┌─────────────────────────┐           ┌─────────────────────────┐
│   DefaultRetryPolicy    │           │  InferenceErrorHandler  │
│                         │           │                         │
│  Handles: Validation    │           │  Handles: Tool calling  │
│  Uses: ErrorFormatter   │           │  Uses: ErrorFormatter   │
└─────────────────────────┘           └─────────────────────────┘
            │                                       │
            └───────────────────┬───────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      CanFormatErrorFeedback                          │
│                                                                      │
│  Implementations:                                                    │
│  • UnifiedErrorFormatter (handles all error types)                   │
│  • ProviderAwareErrorFormatter (OpenAI/Claude/Gemini optimized)      │
│  • Custom implementations                                            │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          ErrorContext                                │
│                                                                      │
│  Unified context for:                                                │
│  • Validation errors (schema failures)                               │
│  • Inference errors (tool calling, JSON, content filter, etc.)       │
│  • Provider detection                                                │
│  • Retry eligibility                                                 │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Implementation Phases

### Phase 1: Foundation (Week 1)

**Days 1-2: Error Context & Categories**
- [ ] Create `ErrorContext` value object
- [ ] Create `ErrorCategory` enum
- [ ] Create `CanFormatErrorFeedback` interface
- [ ] Create `ProviderDetector` utility
- [ ] Unit tests for all above

**Days 3-4: Basic Formatters**
- [ ] Implement `UnifiedErrorFormatter`
- [ ] Implement `ProviderAwareErrorFormatter`
- [ ] Unit tests for formatters

**Day 5: Integration with Validation Errors**
- [ ] Update `DefaultRetryPolicy` to use formatters
- [ ] Wire formatter through `StructuredOutputConfig`
- [ ] Integration tests

### Phase 2: Inference Error Handling (Week 2)

**Days 6-7: InferenceErrorHandler**
- [ ] Implement `InferenceErrorHandler`
- [ ] Wrap inference calls in SyncUpdateGenerator
- [ ] Unit tests

**Days 8-9: Streaming Generators**
- [ ] Update `StreamingUpdatesGenerator` with error handling
- [ ] Update `ModularUpdateGenerator` with error handling
- [ ] Update `PartialUpdateGenerator` with error handling
- [ ] Integration tests for streaming

**Day 10: Fluent API**
- [ ] Add `withErrorFormatter()` to StructuredOutput
- [ ] Add `withInferenceRetries()` to StructuredOutput
- [ ] Wire through factory/builder
- [ ] E2E tests

### Phase 3: Testing & Documentation (Week 3)

**Days 11-12: Comprehensive Testing**
- [ ] Test validation error retry with formatters
- [ ] Test inference error retry with formatters
- [ ] Test provider-specific formatting
- [ ] Test edge cases (non-retryable errors, max retries, etc.)
- [ ] Performance benchmarks

**Days 13-14: Documentation**
- [ ] `docs/advanced/error-handling.md` - comprehensive guide
- [ ] Examples for each error type
- [ ] Update CHANGELOG.md
- [ ] API documentation

**Day 15: Review & Polish**
- [ ] Code review
- [ ] PHPStan/Psalm compliance
- [ ] Final documentation review

---

## Deliverables

### Code Files

**New files (12):**
1. `packages/instructor/src/Contracts/CanFormatErrorFeedback.php`
2. `packages/instructor/src/Errors/ErrorContext.php`
3. `packages/instructor/src/Errors/Enums/ErrorCategory.php`
4. `packages/instructor/src/ErrorFormatters/UnifiedErrorFormatter.php`
5. `packages/instructor/src/ErrorFormatters/ProviderAwareErrorFormatter.php`
6. `packages/instructor/src/Core/InferenceErrorHandler.php`
7. `packages/instructor/src/Utils/ProviderDetector.php`
8. Tests for all above

**Modified files (6):**
1. `packages/instructor/src/RetryPolicy/DefaultRetryPolicy.php`
2. `packages/instructor/src/ResponseIterators/Sync/SyncUpdateGenerator.php`
3. `packages/instructor/src/ResponseIterators/GeneratorBased/StreamingUpdatesGenerator.php`
4. `packages/instructor/src/ResponseIterators/ModularPipeline/ModularUpdateGenerator.php`
5. `packages/instructor/src/Config/StructuredOutputConfig.php`
6. `packages/instructor/src/StructuredOutput.php`

### Documentation

1. `docs/advanced/error-handling.md` - comprehensive error handling guide
2. `examples/A02_Advanced/ErrorHandling/run.php` - working examples
3. CHANGELOG.md updates

---

## Expected Outcomes

### Before v1.5

| Error Type | Retry Support | Error Feedback | Success Rate |
|------------|---------------|----------------|--------------|
| Validation errors | ✅ Yes (limited) | ❌ No | ~60-70% |
| Tool calling errors | ❌ No | ❌ No | N/A (throws) |

### After v1.5

| Error Type | Retry Support | Error Feedback | Success Rate |
|------------|---------------|----------------|--------------|
| Validation errors | ✅ Yes | ✅ Yes | ~80-90% |
| Tool calling errors | ✅ Yes (NEW) | ✅ Yes | ~70-80% |

**Key improvements:**
- 20-30% improvement in validation error recovery
- NEW: Tool calling error recovery (previously 0%)
- Provider-optimized error messages
- Unified error handling architecture

---

## Risk Mitigation

### Risk 1: Breaking Changes to Generator Classes

**Mitigation:**
- Error handling is opt-in via `withInferenceRetries()`
- Default behavior unchanged (exceptions thrown)
- Extensive backward compatibility tests

### Risk 2: Complexity of Two-Layer Error Handling

**Mitigation:**
- Unified `ErrorContext` abstracts differences
- Single `CanFormatErrorFeedback` interface for all error types
- Clear separation of concerns

### Risk 3: Performance Overhead

**Mitigation:**
- Error handling only activates on failures
- Provider detection is simple regex (cached if needed)
- Formatting is string operations (negligible)

### Risk 4: Incorrect Error Categorization

**Mitigation:**
- Conservative categorization (unknown → not retryable)
- Configurable retry eligibility
- Clear logging/debugging support

---

## Open Questions

1. **Should we retry content filter errors?**
   - Currently marked as non-retryable
   - Could be retryable with different prompt
   - Decision: Keep as non-retryable, let users handle

2. **Should we expose raw content to formatters for malformed JSON?**
   - Helps LLM understand what went wrong
   - Privacy/security implications?
   - Decision: Include as optional field in ErrorContext

3. **Should InferenceErrorHandler have its own retry count vs shared?**
   - Current design: Separate retry counts
   - Alternative: Shared total retry budget
   - Decision: Keep separate for now, evaluate after usage

4. **How to handle errors during streaming?**
   - Partial response already received
   - Should we retry or fail?
   - Decision: Retry from beginning (simplest), consider incremental retry in v2.0

---

## Summary

This revised implementation plan addresses the user's valid concern: **a comprehensive error handling strategy must cover BOTH validation errors AND tool calling errors**.

**Key changes from original plan:**
1. ✅ Added `InferenceErrorHandler` for tool calling errors
2. ✅ Unified `ErrorContext` works for both error types
3. ✅ Modified all update generators to support error handling
4. ✅ Extended timeline from 2 weeks to 3 weeks
5. ✅ Added Phase 2 specifically for inference error handling

**This is now a complete solution that handles:**
- Validation errors (schema failures) - enhanced existing
- Tool calling errors (malformed JSON) - **NEW capability**
- Content filter errors (safety) - categorized, not retried
- Max token errors (truncation) - retried with feedback
- Provider-specific formatting - optimized for each LLM
