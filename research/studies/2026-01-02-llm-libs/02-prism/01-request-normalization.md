# Prism - Request Normalization

## Core Files
- `/src/Text/PendingRequest.php` - Fluent builder with trait composition (uses 10+ traits)
- `/src/Text/Request.php` - Immutable normalized request (readonly class)
- `/src/Providers/OpenAI/Maps/MessageMap.php` - OpenAI message normalization
- `/src/Providers/Anthropic/Maps/MessageMap.php` - Anthropic message normalization
- `/src/ValueObjects/Messages/*.php` - UserMessage, AssistantMessage, ToolResultMessage, SystemMessage

## Key Patterns

### Pattern 1: Builder → Immutable Request
- **Mechanism**: Mutable `PendingRequest` → `toRequest()` → Immutable `Request`
- **Code**:
  ```php
  $request = Prism::text()
      ->using('anthropic', 'claude-3-5-sonnet')
      ->withSystemPrompt('You are...')
      ->withMessages($messages)
      ->withMaxTokens(1000)
      ->toRequest();  // Creates immutable Request
  ```
- **Benefits**: Type-safe configuration, cannot accidentally mutate

### Pattern 2: Trait Composition for Configuration
- **Traits Used**:
  - `ConfiguresClient` - HTTP options
  - `ConfiguresGeneration` - Temperature, topP, maxTokens
  - `ConfiguresModels` - Model selection
  - `ConfiguresProviders` - Provider routing
  - `ConfiguresTools` - Tool registration
  - `HasMessages` - Message history
  - `HasPrompts` - System/user prompts
  - `HasProviderOptions` - Provider-specific config
  - `HasProviderTools` - Native provider tools
  - `HasTools` - Prism-wrapped tools
- **Benefits**: Focused concerns, easy to extend

### Pattern 3: MessageMap Per Provider
- **Location**: `src/Providers/{Provider}/Maps/MessageMap.php`
- **OpenAI**:
  ```php
  public static function map(array $messages): array {
      return array_map(fn($msg) => [
          'role' => $msg->role(),
          'content' => self::mapContent($msg),  // [{type, text|image_url}, ...]
      ], $messages);
  }
  ```
- **Anthropic**:
  ```php
  public static function map(array $messages, array $providerOptions): array {
      return array_map(fn($msg) => [
          'role' => $msg->role(),
          'content' => self::mapContent($msg),  // [{type, text|source}, ...]
          'cache_control' => $msg->cacheControl(),  // Anthropic-specific
      ], $messages);
  }

  public static function mapSystemMessages(array $systemPrompts): array|string {
      // Returns system prompt(s) for separate parameter
  }
  ```

### Pattern 4: Request Data Model
- **All Fields** (readonly):
  ```php
  readonly class Request {
      public string $model;
      public string $providerKey;
      public array $systemPrompts;     // SystemMessage[]
      public ?string $prompt;          // User prompt text
      public array $messages;          // Message[] history
      public int $maxSteps;            // Tool call iteration limit
      public ?int $maxTokens;
      public int|float|null $temperature;
      public int|float|null $topP;
      public array $tools;             // Tool[] definitions
      public array $clientOptions;     // HTTP config
      public array $clientRetry;       // Retry strategy
      public string|ToolChoice|null $toolChoice;
      public array $providerOptions;   // Provider-specific
      public array $providerTools;     // Native provider tools
  }
  ```
- **Immutable**: All properties readonly
- **Complete**: Contains everything needed for request

## Provider-Specific Handling

### OpenAI
- **MessageMap**: `src/Providers/OpenAI/Maps/MessageMap.php`
- **Content**: `[{type: 'input_text', text: '...'}, {type: 'input_image', source: {...}}]`
- **Tools**: `ToolMap::map()` → OpenAI function format
- **System**: Merged into messages array at start

### Anthropic
- **MessageMap**: `src/Providers/Anthropic/Maps/MessageMap.php`
- **Content**: `[{type: 'text', text: '...'}, {type: 'image', source: {...}}]`
- **System**: Separate via `MessageMap::mapSystemMessages()`
- **Cache Control**: Supports prompt caching annotations
- **MCP Servers**: Anthropic-specific feature

## Notable Techniques

### 1. Model Catalog Integration
- Each provider has `ModelCatalog` listing available models
- Type-safe model selection via enums
- Auto-completion in IDE

### 2. Provider Options Pass-Through
- `withProviderOptions(['thinking' => ['enabled' => true]])`
- Allows provider-specific features without breaking abstraction
- Accessed via `$request->providerOptions('thinking.enabled')`

### 3. Tool Choice Normalization
- **Enum**: `ToolChoice`: Auto | None | Required | Specific tool name
- **Mapped** per provider (OpenAI vs. Anthropic format)

### 4. Response Format Configuration
- Separate from tool usage
- Supports: JSON object, JSON schema, text
- Provider-specific implementation

## Limitations/Edge Cases

### 1. No Validation
- Builder doesn't validate configuration
- Invalid model names not caught until HTTP
- Type errors only at runtime

### 2. Provider Options Untyped
- Array access with no schema
- Typos in option names silently ignored

### 3. Message Ordering Assumptions
- No automatic role alternation
- Provider-specific requirements not enforced

## Architecture Insights

### Strengths
1. **Immutability**: Request cannot be mutated after creation
2. **Builder pattern**: Clean fluent API
3. **Trait composition**: Focused configuration methods
4. **Provider flexibility**: Easy to add provider-specific features

### Weaknesses
1. **Complexity**: Many traits to understand
2. **No compile-time validation**: Invalid configs not caught early
3. **Message maps separate**: Not part of Request object
