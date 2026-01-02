# NeuronAI - Error Handling

## Core Files
- `/src/Exceptions/*.php` - 14 exception classes
- `/src/HandleStructured.php` - Retry logic with error feedback (lines 101-117)
- Provider HandleChat traits - No explicit error handling

## Key Patterns

### Pattern 1: Exception Hierarchy
```
Exception (PHP base)
├── NeuronException (base framework exception)
│   ├── AgentException (agent-level errors)
│   ├── ProviderException (provider communication errors)
│   ├── ToolException (tool execution errors)
│   ├── ToolMaxTriesException (tool retry limit)
│   ├── ChatHistoryException
│   └── Others...
```

### Pattern 2: Retry with Error Feedback (Structured Output)
- **Location**: HandleStructured.php:101-117
- **Mechanism**: Catch exceptions, send error to LLM, retry
- **Code**:
  ```php
  do {
      try {
          // Attempt
      } catch (RequestException $ex) {
          $error = $ex->getResponse()?->getBody()->getContents() ?? $ex->getMessage();
      } catch (ToolMaxTriesException $ex) {
          throw $ex;  // Don't retry tool exhaustion
      } catch (Exception $ex) {
          $error = $ex->getMessage();
      }
      $maxRetries--;
  } while ($maxRetries >= 0);
  ```
- **ToolMaxTriesException** rethrown immediately (no retry)
- **RequestException** extracts HTTP response body
- **Other exceptions** use message

### Pattern 3: No Provider-Level Error Handling
- HandleChat traits don't catch exceptions
- HTTP errors propagate to caller
- No rate limit detection
- No retry logic at request level

## Provider-Specific Handling

### OpenAI
- **No custom handling** in HandleChat
- Guzzle exceptions propagate
- HTTP 429 (rate limit) not detected
- HTTP 400/500 errors not mapped

### Anthropic
- **No custom handling** in HandleChat
- Same as OpenAI
- Provider-specific errors (overloaded) not detected

## Notable Techniques

### 1. Exception-Based Control Flow
- ToolMaxTriesException used to break retry loop
- Not truly exceptional condition
- Alternative: return sentinel value

### 2. Error Message Extraction
- `$ex->getResponse()?->getBody()->getContents() ?? $ex->getMessage()`
- Tries HTTP body first, falls back to exception message
- Sends raw provider error to LLM

### 3. Observability on Errors
- `$this->notify('error', new AgentError($ex, false))`
- Event dispatched on every exception
- Boolean parameter unclear (false = not fatal?)

## Limitations/Edge Cases

### 1. No HTTP Status Code Handling
- No mapping of 4xx/5xx to exception types
- No rate limit detection (429)
- No retry on transient failures (503)

### 2. No Exponential Backoff
- Immediate retry without delay
- Could trigger rate limits faster
- No jitter

### 3. Broad Exception Catching
- `catch (Exception $ex)` too broad
- Catches programmer errors (TypeError, etc.)
- Retries non-retriable errors

### 4. Error Message to LLM
- Full error text sent to LLM
- May include sensitive data
- No sanitization

### 5. No Circuit Breaker
- Continuous retries possible
- No temporary failure tracking
- Could exhaust resources

### 6. Missing Error Context
- Exceptions don't carry retry attempt number
- No request ID for correlation
- Hard to debug retry loops

## Architecture Insights

### Strengths
1. **Simple**: Minimal error handling complexity
2. **Self-correcting**: LLM receives errors for structured output
3. **Fail-fast**: No hidden error suppression

### Weaknesses
1. **No retry at HTTP level**: Only structured output retries
2. **No rate limit handling**: Will fail immediately
3. **No error categorization**: All errors treated same
4. **No logging**: Relies on external observability

### Comparison
- **vs. Tenacity/Resilience libraries**: No backoff, no circuit breakers
- **vs. Symfony/HTTP**: No HTTP-aware error types
- **vs. Instructor approach**: Similar self-correction for structured output
