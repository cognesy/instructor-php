# Symfony AI - Request Issuing

## Core Files
- `/src/platform/src/Platform.php` - Request dispatcher
- `/src/platform/src/Bridge/*/ModelClient.php` - Provider HTTP clients (25+ providers)
- Symfony HTTP Client component

## Key Patterns

### Pattern 1: ModelClient Interface
- **Contract**:
  ```php
  interface ModelClientInterface {
      public function supports(Model $model): bool;
      public function request(Model $model, array|string $payload, array $options): RawHttpResult;
  }
  ```
- **Type dispatch**: Each client checks model type
- **Single method**: All requests through `request()`

### Pattern 2: Platform Dispatcher
- **Code**:
  ```php
  class Platform {
      public function invoke(string|Model $model, object|array|string $input, array $options): DeferredResult {
          $model = $this->resolveModel($model);
          $payload = $this->contract->createRequestPayload($model, $input);

          foreach ($this->modelClients as $client) {
              if ($client->supports($model)) {
                  $raw = $client->request($model, $payload, $options);
                  return new DeferredResult($raw, $model, $this->resultConverters);
              }
          }

          throw new RuntimeException('No client for model');
      }
  }
  ```
- **Loop**: Checks clients sequentially
- **First match**: Uses first supporting client

### Pattern 3: Bridge Pattern per Provider
- **Anthropic Example**:
  ```php
  class ModelClient implements ModelClientInterface {
      public function request(Model $model, array|string $payload, array $options): RawHttpResult {
          $url = 'https://api.anthropic.com/v1/messages';
          $headers = [
              'x-api-key' => $this->apiKey,
              'anthropic-version' => '2023-06-01',
          ];

          // Provider-specific option handling
          if (isset($options['response_format'])) {
              $options['beta_features'][] = 'structured-outputs-2025-11-13';
              $options['output_format'] = [
                  'type' => 'json_schema',
                  'schema' => $options['response_format']['json_schema']['schema'],
              ];
          }

          return new RawHttpResult($this->httpClient->request('POST', $url, [
              'headers' => $headers,
              'json' => array_merge($options, $payload),
          ]));
      }
  }
  ```

### Pattern 4: RawHttpResult Wrapper
- **Purpose**: Wraps Symfony ResponseInterface
- **Methods**:
  - `getData()` - Parsed response array
  - `getDataStream()` - Generator for streaming
  - `getObject()` - Raw ResponseInterface
- **Lazy**: Data parsed on-demand

## Provider-Specific Handling

### Anthropic
- **URL**: `https://api.anthropic.com/v1/messages`
- **Auth**: `x-api-key` header
- **Version**: `anthropic-version` header
- **Features**: Beta features via headers
- **Structured**: Converts response_format to Anthropic beta

### OpenAI/GPT
- **URL**: `https://api.openai.com/v1/chat/completions`
- **Auth**: `Authorization: Bearer {key}`
- **Streaming**: `stream: true` parameter

### Gemini
- **URL**: `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`
- **Auth**: `x-goog-api-key` header
- **Different**: URL includes model name
- **Wrapper**: `generationConfig` wrapper for options

## Notable Techniques

### 1. Model Catalog
- **Interface**: `ModelCatalogInterface`
- **Methods**: `getModel(string $name): Model`, `getModels(): array`
- **Registration**: Each bridge registers its models
- **Discovery**: Platform queries catalogs

### 2. Event System
- **Events**: `InvocationEvent` (before), `ResultEvent` (after)
- **Hooks**: Modify model, input, or result
- **Location**: Platform dispatches events

### 3. Option Merging
- **Pattern**: `array_merge($options, $payload)`
- **Allows**: Provider options override payload
- **Risk**: Payload can be overridden by options

## Architecture Insights

### Strengths
1. **Bridge pattern**: Clean provider separation
2. **Type dispatch**: Model-based routing
3. **Event hooks**: Extensibility points
4. **Catalog**: Centralized model registry

### Weaknesses
1. **Linear search**: Checks all clients
2. **No caching**: Model resolution every call
3. **Symfony HTTP**: Tied to Symfony ecosystem
