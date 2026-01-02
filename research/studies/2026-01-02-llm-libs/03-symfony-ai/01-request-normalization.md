# Symfony AI - Request Normalization

## Core Files
- `/src/platform/src/Contract.php` - Normalization orchestrator
- `/src/platform/src/Contract/Normalizer/*` - Generic normalizers (15+ classes)
- `/src/platform/src/Bridge/Anthropic/Contract/*` - Anthropic-specific normalizers
- `/src/platform/src/Bridge/OpenAi/Contract/Gpt/*` - OpenAI-specific normalizers
- Symfony Serializer component (framework dependency)

## Key Patterns

### Pattern 1: Symfony Serializer-Based Normalization
- **Mechanism**: Uses Symfony's Serializer component with stacked normalizers
- **Priority System**: Provider-specific normalizers registered before generic ones
- **Code**:
  ```php
  class Contract {
      protected SerializerInterface $serializer;

      public function createRequestPayload(Model $model, object|array|string $input): string|array {
          // Normalizes using registered normalizers
          return $this->serializer->normalize($input, context: ['model' => $model]);
      }
  }
  ```

### Pattern 2: Normalizer Hierarchy with Context
- **Interface**: All implement `NormalizerInterface`
- **Methods**:
  - `supportsNormalization($data, ?string $format, array $context): bool`
  - `normalize($data, ?string $format, array $context): mixed`
- **Context**: Carries model type for provider-specific routing
- **Example** (UserMessageNormalizer):
  ```php
  public function supportsNormalization($data, ?string $format, array $context): bool {
      if (!$data instanceof UserMessage) return false;

      $model = $context['model'] ?? null;
      if (!$model instanceof Claude) return false;  // Anthropic check

      return true;
  }

  public function normalize($data, ?string $format, array $context): array {
      return [
          'role' => 'user',
          'content' => $this->normalizeContent($data->getContent()),
      ];
  }
  ```

### Pattern 3: Normalizer Stacking
- **Generic**: `/Contract/Normalizer/UserMessageNormalizer.php`
- **Provider-specific**: `/Bridge/Anthropic/Contract/UserMessageNormalizer.php`
- **Resolution**: Symfony checks normalizers in registration order
- **Override**: Provider normalizer matches first (higher priority)

### Pattern 4: Model-Based Routing
- **Model classes**: `Claude`, `Gpt`, `Gemini`, etc.
- **Context**: `['model' => $claudeInstance]`
- **Check**: Normalizers test `$model instanceof Claude`
- **Benefits**: Type-safe provider detection

## Provider-Specific Handling

### Anthropic
- **Normalizers**: Custom for Claude models
- **System Prompt**: `MessageMap::mapSystemMessages()` separates system from messages
- **Content Blocks**: Converts to `[{type: 'text'}, {type: 'image', source: {...}}]`
- **Cache Control**: Supports prompt caching
- **MCP Servers**: Provider-specific parameter

### OpenAI
- **Normalizers**: Custom for GPT models
- **System Prompt**: Merged into messages array
- **Content**: `[{type: 'text', text: '...'}, {type: 'image_url', image_url: {...}}]`
- **Tools**: OpenAI function format
- **Response Format**: Native JSON schema support

### Gemini
- **Different structure**: `parts` instead of `content`
- **Role mapping**: `user` → `user`, `assistant` → `model`
- **Tools**: `functionDeclarations` format

## Notable Techniques

### 1. ModelContractNormalizer Base Class
- **Abstract base**: Provider normalizers extend this
- **Common logic**: Type checking, content normalization
- **Override points**: `normalize()` for provider-specific format

### 2. Progressive Content Normalization
- **String**: `"text"` → `[{type: 'text', text: 'text'}]`
- **Array**: Mixed content types normalized recursively
- **Multimodal**: Images, documents handled per provider

### 3. Tool Definition Normalization
- **Generic ToolNormalizer**: Extracts JSON schema from Tool objects
- **Provider-specific**: Maps to provider format (function vs. input_schema)

### 4. Lazy Serializer Construction
- **Pattern**: Serializer built with normalizers on-demand
- **Registration**: Provider bridge registers its normalizers
- **Extension**: Easy to add new providers

## Limitations/Edge Cases

### 1. Symfony Dependency
- Requires Symfony Serializer component
- Heavy dependency for normalization
- Non-Symfony projects need extra setup

### 2. Priority Management
- Registration order matters
- No explicit priority configuration
- Must ensure provider normalizers registered first

### 3. Context Pollution
- Context array grows with metadata
- No schema for context structure
- Easy to have conflicts

### 4. Type Checking Overhead
- Every normalizer checks `supportsNormalization()`
- Linear search through normalizers
- Could be slow with many normalizers

## Architecture Insights

### Strengths
1. **Symfony ecosystem**: Leverages mature serialization
2. **Override system**: Clean provider-specific customization
3. **Extensible**: Easy to add normalizers
4. **Type-safe routing**: Model classes for dispatch

### Weaknesses
1. **Complex**: Many layers of abstraction
2. **Learning curve**: Must understand Symfony Serializer
3. **Performance**: Normalization overhead
4. **Debugging**: Hard to trace which normalizer runs
