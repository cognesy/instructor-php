# Prism - Error Handling

## Core Files
- `/src/Exceptions/*` - Exception hierarchy
- `/src/Providers/Provider.php` - Base error handling

## Key Patterns

### Pattern 1: HTTP Status Code Mapping
- **Location**: `Provider::handleRequestException()`
- **Code**:
  ```php
  public function handleRequestException(string $model, RequestException $e): never {
      match ($e->response->getStatusCode()) {
          413 => throw PrismRequestTooLargeException::make(...),
          429 => throw PrismRateLimitedException::make([]),
          529 => throw PrismProviderOverloadedException::make(...),
          default => throw PrismException::providerRequestError(...),
      };
  }
  ```
- **Specific exceptions**: Rate limits, overload, payload size

### Pattern 2: Rate Limit Extraction
- **Parses headers**: `retry-after`, `x-ratelimit-*`
- **Metadata**: Captures limit info for retry logic
- **Returns**: Structured exception with retry delay

## Notable Techniques

### 1. Exception Factory Methods
- `::make()` static methods
- Consistent construction
- Includes metadata

### 2. Provider Error Messages
- Extracts provider-specific error details
- Includes in exception message
- Preserves original for debugging

## Architecture Insights

### Strengths
1. **Status code awareness**: Proper HTTP error mapping
2. **Rate limit handling**: Extracts retry info
3. **Metadata rich**: Exceptions carry context

### Weaknesses
1. **No retry implementation**: Just detection
2. **No circuit breaker**: No failure tracking
