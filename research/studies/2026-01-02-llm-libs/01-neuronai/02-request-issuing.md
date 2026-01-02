# NeuronAI - Request Issuing

## Core Files
- `/src/Providers/HasGuzzleClient.php` - HTTP client management trait (49 lines)
- `/src/Providers/HttpClientOptions.php` - HTTP client configuration VO
- `/src/Providers/OpenAI/OpenAI.php` - OpenAI provider implementation
- `/src/Providers/Anthropic/Anthropic.php` - Anthropic provider implementation
- `/src/Providers/OpenAI/HandleChat.php` - OpenAI chat request logic (trait)
- `/src/Providers/Anthropic/HandleChat.php` - Anthropic chat request logic (trait)

## Key Patterns

### Pattern 1: Trait-Based HTTP Client Initialization
- **Location**: Constructor in each provider class (OpenAI:46-67, Anthropic:45-67)
- **Mechanism**: Guzzle Client instantiated in constructor with provider-specific config
- **Code**:
  ```php
  public function __construct(
      protected string $key,
      protected string $model,
      protected array $parameters = [],
      protected ?HttpClientOptions $httpOptions = null,
  ) {
      $config = [
          'base_uri' => trim($this->baseUri, '/').'/',
          'headers' => [/* provider-specific */],
      ];

      if ($this->httpOptions instanceof HttpClientOptions) {
          $config = $this->mergeHttpOptions($config, $this->httpOptions);
      }

      $this->client = new Client($config);
  }
  ```
- **No dependency injection**: Client created internally, not injected

### Pattern 2: Async-First Request Pattern
- **Location**: `HandleChat` trait in each provider
- **Mechanism**:
  - `chat()` → `chatAsync()->wait()` (sync wraps async)
  - All requests use `postAsync()` returning `PromiseInterface`
  - Response parsing in `->then()` callback
- **Code**:
  ```php
  public function chat(array $messages): Message {
      return $this->chatAsync($messages)->wait();
  }

  public function chatAsync(array $messages): PromiseInterface {
      $json = [/* request body */];

      return $this->client->postAsync('chat/completions', [RequestOptions::JSON => $json])
          ->then(function (ResponseInterface $response) {
              // Response parsing
          });
  }
  ```

### Pattern 3: Request Body Assembly
- **Location**: `chatAsync()` in `HandleChat` traits
- **Mechanism**: Array spread operator for parameter merging
- **Code**:
  ```php
  $json = [
      'model' => $this->model,
      'messages' => $this->messageMapper()->map($messages),
      ...$this->parameters  // Spreads user-provided params
  ];
  ```
- **Allows override**: User params can override defaults via spread

### Pattern 4: Conditional System Prompt Injection
- **OpenAI** (HandleChat.php:29-31):
  ```php
  if (isset($this->system)) {
      array_unshift($messages, new Message(MessageRole::SYSTEM, $this->system));
  }
  ```
  - System prompt **prepended** to messages array

- **Anthropic** (HandleChat.php:34-36):
  ```php
  if (isset($this->system)) {
      $json['system'] = $this->system;
  }
  ```
  - System prompt as **separate parameter**

## Provider-Specific Handling

### OpenAI
- **Base URI**: `https://api.openai.com/v1`
- **Endpoint**: `POST /chat/completions`
- **Headers**:
  ```php
  'Accept' => 'application/json',
  'Content-Type' => 'application/json',
  'Authorization' => 'Bearer ' . $this->key,
  ```
- **Required Params**:
  - `model`
  - `messages`
- **Optional Params**: All spread from `$this->parameters`
- **System Prompt**: Injected as first message with `role: "system"`

### Anthropic
- **Base URI**: `https://api.anthropic.com/v1/`
- **Endpoint**: `POST /messages`
- **Headers**:
  ```php
  'Content-Type' => 'application/json',
  'x-api-key' => $this->key,
  'anthropic-version' => $version,  // Defaults to '2023-06-01'
  ```
- **Required Params**:
  - `model`
  - `max_tokens` (Anthropic-specific requirement)
  - `messages`
- **Optional Params**: All spread from `$this->parameters`
- **System Prompt**: Separate `system` parameter

### Tools Attachment
- **Both providers**:
  ```php
  if (!empty($this->tools)) {
      $json['tools'] = $this->toolPayloadMapper()->map($this->tools);
  }
  ```
- Only added if tools array is non-empty
- Uses provider-specific `ToolPayloadMapper`

## Notable Techniques

### 1. Base URI Trailing Slash Normalization
- **Code**: `trim($this->baseUri, '/').'/'`
- **Why**: Ensures consistent URI regardless of how baseUri is defined
- **Location**: Both OpenAI:54 and Anthropic:54

### 2. HTTP Options Merging
- **Pattern**: `mergeHttpOptions(array $config, HttpClientOptions $options)`
- **Supported Options**:
  - `headers` - Additional headers (array merged)
  - `timeout` - Request timeout (overrides)
  - `connect_timeout` - Connection timeout
  - `handler` - Custom Guzzle HandlerStack
  - `proxy` - Proxy configuration
- **Location**: `HasGuzzleClient.php:26-47`
- **Usage**: Allows user customization without exposing full Guzzle config

### 3. Lazy Mapper Initialization
- **Pattern**: Null coalescing assignment
  ```php
  public function messageMapper(): MessageMapperInterface {
      return $this->messageMapper ?? $this->messageMapper = new MessageMapper();
  }
  ```
- **Why**: Only instantiate if needed
- **Location**: Both providers

### 4. Promise-Based Response Handling
- **Pattern**: `->then(function (ResponseInterface $response) { ... })`
- **Advantages**:
  - Supports async workflows
  - Chainable promise transformations
  - Easy error handling with `->otherwise()`

## Limitations/Edge Cases

### 1. No Request Validation
- Model name not validated against provider's available models
- Parameters not checked for provider compatibility
- Invalid params silently passed to API (fail at HTTP level)

### 2. Hard-Coded Endpoints
- Endpoint strings hard-coded in traits (`'chat/completions'`, `'messages'`)
- No endpoint customization for alternative APIs
- Cannot easily add new endpoints without new traits

### 3. No Retry Logic
- No built-in retry on transient failures
- Must implement in custom HandlerStack if needed
- No exponential backoff

### 4. Client Instantiation Location
- Client created in constructor, not injected
- Hard to mock for testing
- Cannot reuse client across provider instances

### 5. System Prompt Inconsistency
- OpenAI: System prompt modifies messages array (side effect)
- Anthropic: System prompt as request parameter (clean)
- Different handling complicates unified abstraction

### 6. Base URI Mutability
- `baseUri` is protected property, not constructor param
- Subclasses can override but users cannot customize without extending
- Example: Cannot easily use different OpenAI-compatible endpoints

### 7. No Request ID Tracking
- No correlation ID generation
- No way to track requests for debugging
- No request logging hooks

## Architecture Insights

### Strengths
1. **Trait composition**: Clean separation of HTTP vs. chat vs. stream logic
2. **Async-first**: Supports concurrent requests naturally
3. **Simple parameter passing**: Spread operator for provider-specific params
4. **HTTP options**: Configurable timeouts, proxies without exposing Guzzle

### Weaknesses
1. **Tight coupling**: Provider classes know about Guzzle Client directly
2. **No interface for HTTP**: Cannot swap HTTP client implementation
3. **Constructor complexity**: Many parameters, long signatures
4. **No builder pattern**: Cannot fluently configure requests

### Comparison to Typical Approaches
- **vs. Repository pattern**: Direct HTTP calls, no abstraction layer
- **vs. Command pattern**: No command objects, direct method calls
- **vs. Factory pattern**: No factory for client creation
- **vs. Middleware**: Uses Guzzle HandlerStack but not documented/encouraged

## Request Flow
1. User calls `provider->chat($messages)` or `provider->chatAsync($messages)`
2. System prompt conditionally injected
3. Message normalization via `MessageMapper`
4. Request body assembled with spread operator
5. Tools conditionally attached
6. `$this->client->postAsync()` called with JSON body
7. Promise returned (or waited for sync)
8. Response parsed in `->then()` callback
