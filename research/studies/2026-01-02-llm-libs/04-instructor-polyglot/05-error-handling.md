# InstructorPHP Polyglot - Error Handling

## Core Files
- `/src/Inference/Drivers/BaseInferenceDriver.php` - HTTP status code checking
- `/packages/http-client/src/Exceptions/` - HTTP exception hierarchy
- `/src/Inference/PendingInference.php` - Inference execution error handling

## Key Patterns

### Pattern 1: HTTP Status Code Detection
- **Location**: `BaseInferenceDriver::makeHttpResponse()`
- **Code**:
  ```php
  protected function makeHttpResponse(HttpRequest $request): HttpResponse {
      try {
          $httpResponse = $this->httpClient->withRequest($request)->get();
      } catch (Exception $e) {
          $this->dispatchInferenceSendingFailed($request, $e);
          throw $e;
      }

      if ($httpResponse->statusCode() >= 400) {
          $this->dispatchInferenceResponseFailed($httpResponse);
          throw new RuntimeException('HTTP request failed with status code ' . $httpResponse->statusCode());
      }
      return $httpResponse;
  }
  ```
- **Simple**: Throws generic `RuntimeException` for any 4xx/5xx
- **No retry**: Just fails immediately

### Pattern 2: Event-Based Error Reporting
- **Events**: `InferenceFailed`, `InferenceProcessingFailed`, `InferenceSendingFailed`, `InferenceStreamFailed`
- **Code**:
  ```php
  private function dispatchInferenceResponseFailed(HttpResponse $httpResponse): void {
      $this->events->dispatch(new InferenceFailed([
          'context' => 'HTTP response received with error status',
          'statusCode' => $httpResponse->statusCode(),
          'headers' => $httpResponse->headers(),
          'body' => $httpResponse->body(),
      ]));
  }
  ```
- **Pattern**: Dispatch event, then throw exception
- **Usage**: Allows logging/monitoring before failure

### Pattern 3: HTTP Client Exception Hierarchy
- **File**: `/packages/http-client/src/Exceptions/HttpExceptionFactory.php`
- **Code**:
  ```php
  public static function fromStatusCode(
      int $statusCode,
      ?HttpRequest $request = null,
      ?HttpResponse $response = null,
      ?float $duration = null,
      ?Throwable $previous = null,
  ): HttpRequestException {
      return match (true) {
          $statusCode >= 400 && $statusCode < 500 => new HttpClientErrorException(...),
          $statusCode >= 500 => new ServerErrorException(...),
          default => throw new InvalidArgumentException("Invalid HTTP status code: {$statusCode}"),
      };
  }
  ```
- **Exception Types**:
  - `HttpClientErrorException` (4xx)
  - `ServerErrorException` (5xx)
  - `ConnectionException`
  - `TimeoutException`
  - `NetworkException`

### Pattern 4: Finish Reason Checking
- **Location**: `PendingInference::get()`
- **Code**:
  ```php
  if (!$response->finishReason()->isOk()) {
      throw new \RuntimeException('Inference execution failed: ' . $response->finishReason()->value);
  }
  ```
- **Validates**: Completion status
- **Throws**: If not successful completion

## Error Categories

### 1. HTTP Errors
- **Status 400+**: Generic `RuntimeException`
- **No details**: Just status code
- **No differentiation**: Rate limit = server error = auth error

### 2. Connection Errors
- **Handled by**: HTTP client layer
- **Types**: `ConnectionException`, `TimeoutException`, `NetworkException`
- **Propagates**: Through to caller

### 3. Response Parsing Errors
- **Location**: `BaseInferenceDriver::httpResponseToInference()`
- **Code**:
  ```php
  try {
      $inferenceResponse = $this->responseTranslator->fromResponse($httpResponse);
      if ($inferenceResponse === null) {
          throw new RuntimeException('Failed to translate HTTP response to InferenceResponse');
      }
  } catch (Exception $e) {
      $this->dispatchInferenceProcessingFailed($httpResponse, $e);
      throw $e;
  }
  ```
- **Generic**: Just "failed to translate"
- **No details**: Doesn't include parse error info

### 4. Streaming Errors
- **Pattern**: Same as regular errors
- **Event**: `InferenceStreamFailed`
- **Propagates**: Throws exception mid-stream

## Notable Techniques

### 1. Event-Then-Throw Pattern
- **Always**: Dispatch event before throwing
- **Benefits**: Logging, metrics, debugging
- **Consistent**: Applied to all error paths

### 2. Generic RuntimeException
- **Simple**: No custom exception types in Polyglot
- **Trade-off**: Easy but less specific
- **Relies on**: HTTP client for detailed exceptions

### 3. No Retry Logic
- **No built-in retries**: Caller must implement
- **No backoff**: No rate limit handling
- **Event-based**: Could implement retry via event listeners

## Architecture Insights

### Strengths
1. **Event-based**: Rich error context via events
2. **Consistent**: Same pattern across all error types
3. **Simple**: No complex retry logic to maintain

### Weaknesses
1. **Generic exceptions**: Hard to handle specific errors
2. **No retry**: Must implement externally
3. **No rate limit detection**: Treats 429 like any other error
4. **Limited context**: Just status code, no provider error messages

## Comparison to Other Libraries

### Minimal Approach
- **NeuronAI**: Custom exception hierarchy, retry with exponential backoff
- **Prism**: Exception types per error category
- **Symfony AI**: ResultConverter with specific exceptions
- **InstructorPHP Polyglot**: Generic RuntimeException + events

### Trade-offs
- **Pro**: Simple, no retry state management
- **Con**: Caller must handle all retry logic
- **Pro**: Event-based allows custom error handling
- **Con**: Exceptions don't carry provider error details
