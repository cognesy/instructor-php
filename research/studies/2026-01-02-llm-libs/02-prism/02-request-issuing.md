# Prism - Request Issuing

## Core Files
- `/src/Providers/OpenAI/Handlers/Text.php` - OpenAI request handler
- `/src/Providers/Anthropic/Handlers/Text.php` - Anthropic request handler
- `/src/Providers/OpenAI/OpenAI.php` - Provider initialization
- `/src/Providers/Anthropic/Anthropic.php` - Provider initialization

## Key Patterns

### Pattern 1: Handler Pattern
- **Mechanism**: Each provider has `Handlers/{Action}.php` classes
- **Code**:
  ```php
  class OpenAI extends Provider {
      public function text(TextRequest $request): TextResponse {
          $handler = new Text($this->client(
              $request->clientOptions(),
              $request->clientRetry()
          ));
          return $handler->handle($request);
      }
  }
  ```
- **Separation**: Provider class initializes, Handler executes

### Pattern 2: Laravel HTTP Client
- **Uses**: Illuminate\Http\Client (not Guzzle directly)
- **Benefits**: Fluent API, Laravel integration, testing helpers
- **Code**:
  ```php
  protected function client(array $options = [], array $retry = []): PendingRequest {
      return Http::withHeaders([
              'x-api-key' => $this->apiKey,
              'anthropic-version' => $this->apiVersion,
          ])
          ->withOptions($options)
          ->when($retry !== [], fn($client) => $client->retry(...$retry))
          ->baseUrl($this->url);
  }
  ```

### Pattern 3: Payload Building in Handler
- **Location**: `buildHttpRequestPayload()` static method
- **OpenAI**:
  ```php
  protected static function buildHttpRequestPayload(PrismRequest $request): array {
      return [
          'model' => $request->model(),
          'input' => (new MessageMap($request->messages(), $request->systemPrompts()))(),
          'max_output_tokens' => $request->maxTokens(),
          'temperature' => $request->temperature(),
          'tools' => static::buildTools($request),
      ];
  }
  ```
- **Anthropic**:
  ```php
  protected static function buildHttpRequestPayload(PrismRequest $request): array {
      return [
          'model' => $request->model(),
          'system' => MessageMap::mapSystemMessages($request->systemPrompts()),
          'messages' => MessageMap::map($request->messages(), $request->providerOptions()),
          'max_tokens' => $request->maxTokens() ?? 64000,  // Required
          'thinking' => $request->providerOptions('thinking.enabled'),
          'mcp_servers' => $request->providerOptions('mcp_servers'),
      ];
  }
  ```

## Provider-Specific Handling

### OpenAI
- **Endpoint**: `POST /responses`
- **Headers**: Standard Authorization Bearer
- **Required**: `model`, `input`
- **Optional**: All generation params

### Anthropic
- **Endpoint**: `POST /messages`
- **Headers**: `x-api-key`, `anthropic-version`, optional `anthropic-beta`
- **Required**: `model`, `max_tokens`, `messages`
- **System**: Separate parameter (not in messages)
- **Features**: Thinking mode, MCP servers

## Notable Techniques

### 1. Conditional Retry
- `->when($retry !== [], fn($client) => $client->retry(...$retry))`
- Only adds retry if specified
- Uses Laravel's retry mechanism

### 2. Provider-Specific Options
- `$request->providerOptions('thinking.enabled')`
- Type-unsafe but flexible
- Allows new features without API changes

### 3. HTTP Options Pass-Through
- Full Guzzle options available
- Headers, timeouts, proxies
- Merged at client creation

## Architecture Insights

### Strengths
1. **Handler separation**: Clean request execution
2. **Laravel HTTP**: Modern API, good DX
3. **Retry support**: Built-in resilience

### Weaknesses
1. **Laravel dependency**: Requires Laravel packages
2. **Handler instantiation**: New instance per request
3. **Static methods**: Hard to mock/test
