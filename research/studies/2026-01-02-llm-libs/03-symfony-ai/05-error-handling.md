# Symfony AI - Error Handling

## Core Files
- `/src/platform/src/Bridge/*/ResultConverter.php` - Error detection in converters

## Key Pattern

### HTTP Status Code Handling in ResultConverter
- **Code**:
  ```php
  public function convert(RawHttpResult $result, array $options): ResultInterface {
      $response = $result->getObject();

      if (429 === $response->getStatusCode()) {
          throw new RateLimitExceededException($retryAfter);
      }

      if ($response->getStatusCode() >= 500) {
          throw new ServerErrorException(...);
      }

      // ... parsing
  }
  ```
- **Early detection**: Before parsing response body
- **Specific exceptions**: Rate limit, server error, etc.

## Notable Techniques

### 1. Retry-After Header Extraction
- Parses `retry-after` header
- Includes in exception
- Caller can implement retry logic

### 2. Provider Error Messages
- Extracts error from response body
- Includes in exception message
- Preserves provider details

## Architecture Insights

### Strengths
1. **Early detection**: Checks status before parsing
2. **Rich exceptions**: Includes retry metadata
3. **Provider-aware**: Extracts provider errors

### Weaknesses
1. **No retry implementation**: Just detection
2. **No backoff**: Caller must implement
