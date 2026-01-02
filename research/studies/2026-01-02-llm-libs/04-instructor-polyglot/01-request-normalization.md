# InstructorPHP Polyglot - Request Normalization

## Core Files
- `/src/Inference/Drivers/OpenAI/OpenAIBodyFormat.php` - Request body construction
- `/src/Inference/Drivers/OpenAI/OpenAIMessageFormat.php` - Message normalization
- `/src/Inference/Drivers/Anthropic/AnthropicBodyFormat.php` - Anthropic request body
- `/src/Inference/Drivers/Anthropic/AnthropicMessageFormat.php` - Anthropic messages
- `/src/Inference/Data/InferenceRequest.php` - Normalized request data model

## Key Patterns

### Pattern 1: BodyFormat + MessageFormat Composition
- **Two-layer**: BodyFormat uses MessageFormat
- **Code**:
  ```php
  class OpenAIBodyFormat implements CanMapRequestBody {
      public function __construct(
          protected LLMConfig $config,
          protected CanMapMessages $messageFormat,
      ) {}

      public function toRequestBody(InferenceRequest $request): array {
          return [
              'model' => $request->model() ?: $this->config->model,
              'max_completion_tokens' => $request->maxTokens(),
              'messages' => $this->messageFormat->map($request->messages()),
              'tools' => $this->toTools($request),
              'response_format' => $this->toResponseFormat($request),
          ];
      }
  }
  ```
- **Separation**: Body structure vs. message format

### Pattern 2: Interface-Based Composition
- **Interfaces**:
  - `CanMapRequestBody` - Body construction
  - `CanMapMessages` - Message normalization
  - `CanMapTools` - Tool definitions
- **Benefits**: Testable, swappable implementations

### Pattern 3: InferenceRequest Data Model
- **Structure**:
  ```php
  class InferenceRequest {
      protected string $model;
      protected array $messages;
      protected array $tools;
      protected array $options;
      protected ?ResponseFormat $responseFormat;
      protected ?OutputMode $outputMode;
      protected ?string $toolChoice;
  }
  ```
- **Mutable**: Not readonly (can be modified)
- **Methods**: Getters and `withXxx()` modifiers

### Pattern 4: Capability Detection
- **Methods in BodyFormat**:
  - `supportsToolSelection(InferenceRequest $request): bool`
  - `supportsStructuredOutput(InferenceRequest $request): bool`
  - `supportsAlternatingRoles(InferenceRequest $request): bool`
- **Usage**: Conditional feature application
- **Example**:
  ```php
  protected function toResponseFormat(InferenceRequest $request): array {
      if (!$this->supportsStructuredOutput($request)) {
          return [];
      }
      // ... build response format
  }
  ```

## Provider-Specific Handling

### OpenAI
- **MessageFormat**: `[{role, content: [{type, text|image_url}]}]`
- **BodyFormat**:
  - Converts `max_tokens` → `max_completion_tokens`
  - Handles `response_format` with JSON schema
  - Tool choice as object or string
- **Cache**: `withCacheApplied()` for prompt caching

### Anthropic
- **MessageFormat**: `[{role, content: [{type, text|source}]}]`
- **BodyFormat**:
  - System prompt separate
  - Required `max_tokens`
  - Different tool format

## Notable Techniques

### 1. Response Format Handler Pattern
- **Fluent API**:
  ```php
  $responseFormat = $request->responseFormat()
      ->withToJsonObjectHandler(fn() => ['type' => 'json_object'])
      ->withToJsonSchemaHandler(fn() => [
          'type' => 'json_schema',
          'json_schema' => [
              'name' => $request->responseFormat()->schemaName(),
              'schema' => $this->removeDisallowedEntries($request->responseFormat()->schema()),
              'strict' => $request->responseFormat()->strict(),
          ],
      ]);

  return $responseFormat->as($mode);
  ```
- **Type-safe**: Handler per output mode
- **Provider-specific**: Different handlers per provider

### 2. Schema Filtering
- **Pattern**: `removeDisallowedEntries(array $schema)`
- **Removes**: Provider-incompatible fields (`x-title`, `x-php-class`)
- **Recursive**: Walks entire schema tree

### 3. Empty Value Filtering
- **Pattern**: `filterEmptyValues(array $data)`
- **Removes**: `null`, `[]`, `''`
- **Cleaner**: Reduces payload size

### 4. Message Role Merging
- **Condition**: If provider doesn't support alternating roles
- **Code**:
  ```php
  $messages = match($this->supportsAlternatingRoles($request)) {
      false => Messages::fromArray($request->messages())->toMergedPerRole()->toArray(),
      true => $request->messages(),
  };
  ```
- **Gemini**: Requires strict role alternation

## Limitations/Edge Cases

### 1. Mutable Request
- `InferenceRequest` can be modified
- Not thread-safe
- Cache application modifies request

### 2. No Validation
- Invalid model names not caught
- Tool schemas not validated
- Runtime errors from provider

### 3. Capability Methods Not Enforced
- Just return true/false
- No exception if unsupported feature used
- Silent failures possible

## Architecture Insights

### Strengths
1. **Composition**: BodyFormat + MessageFormat separation
2. **Interfaces**: Clear contracts
3. **Capability detection**: Feature availability checks
4. **Response format**: Type-safe handlers

### Weaknesses
1. **Mutability**: Request can change
2. **No builder**: Direct property access
3. **Limited validation**: Runtime only
