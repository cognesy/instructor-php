# InstructorPHP Polyglot - Request Issuing

## Core Files
- `/src/Inference/Drivers/OpenAI/OpenAIRequestAdapter.php` - HTTP request construction
- `/src/Inference/Drivers/Anthropic/AnthropicRequestAdapter.php` - Anthropic HTTP
- `/src/Inference/Drivers/BaseInferenceDriver.php` - Base driver class
- `/src/Inference/Config/LLMConfig.php` - Configuration object

## Key Patterns

### Pattern 1: RequestAdapter Pattern
- **Interface**: `CanTranslateInferenceRequest`
- **Method**: `toHttpRequest(InferenceRequest $request): HttpRequest`
- **Code**:
  ```php
  class OpenAIRequestAdapter implements CanTranslateInferenceRequest {
      public function __construct(
          protected LLMConfig $config,
          protected CanMapRequestBody $bodyFormat,
      ) {}

      public function toHttpRequest(InferenceRequest $request): HttpRequest {
          return new HttpRequest(
              url: $this->toUrl($request),
              method: 'POST',
              headers: $this->toHeaders($request),
              body: $this->bodyFormat->toRequestBody($request),
              options: ['stream' => $request->isStreamed()],
          );
      }
  }
  ```
- **Separation**: Adapter constructs HttpRequest, BodyFormat constructs payload

### Pattern 2: HttpRequest Value Object
- **Immutable DTO**: Contains all HTTP request data
- **Fields**: url, method, headers, body, options
- **Benefits**: Can serialize, inspect, test without HTTP

### Pattern 3: Configuration-Based URL Construction
- **Code**:
  ```php
  protected function toUrl(InferenceRequest $request): string {
      return "{$this->config->apiUrl}{$this->config->endpoint}";
  }
  ```
- **Config**: Stores base URL + endpoint
- **Flexible**: Easy to change endpoints

### Pattern 4: Driver Initialization
- **Code**:
  ```php
  class OpenAIDriver extends BaseInferenceDriver {
      public function __construct(
          protected LLMConfig $config,
          protected HttpClient $httpClient,
          protected EventDispatcherInterface $events,
      ) {
          $this->requestTranslator = new OpenAIRequestAdapter(
              $config,
              new OpenAIBodyFormat($config, new OpenAIMessageFormat())
          );
          $this->responseTranslator = new OpenAIResponseAdapter(
              new OpenAIUsageFormat()
          );
      }
  }
  ```
- **Constructor injection**: Config, HTTP client, events
- **Manual wiring**: Creates adapters internally

## Provider-Specific Handling

### OpenAI
- **URL**: `{apiUrl}/chat/completions`
- **Headers**:
  ```php
  'Authorization' => "Bearer {$apiKey}",
  'Content-Type' => 'application/json; charset=utf-8',
  'Accept' => 'application/json',
  'OpenAI-Organization' => $org,  // Optional
  'OpenAI-Project' => $project,   // Optional
  ```

### Anthropic
- **URL**: `{apiUrl}/messages`
- **Headers**:
  ```php
  'x-api-key' => $apiKey,
  'anthropic-version' => $version,
  'Content-Type' => 'application/json',
  ```

## Notable Techniques

### 1. Metadata in Config
- **Pattern**: `$config->metadata['organization']`
- **Usage**: Optional provider-specific values
- **Filtering**: `array_filter()` removes empty values

### 2. Options Pass-Through
- **Pattern**: `options: ['stream' => $request->isStreamed()]`
- **HTTP client**: Receives as-is
- **Extensible**: Can add timeouts, retries, etc.

### 3. Event Dispatching
- **Pattern**: Events injected into driver
- **Usage**: Can emit request/response events
- **Hook points**: Before/after HTTP

## Architecture Insights

### Strengths
1. **Adapter pattern**: Clean separation
2. **Value objects**: Testable without HTTP
3. **Configuration**: Centralized settings

### Weaknesses
1. **Manual wiring**: Adapters created in constructor
2. **No DI**: Hard to swap implementations
3. **Limited events**: Not fully utilized
