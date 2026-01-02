# Symfony AI - Response Extraction

## Core Files
- `/src/platform/src/Result/*` - Result type hierarchy (TextResult, StreamResult, ToolCallResult, etc.)
- `/src/platform/src/Bridge/*/ResultConverter.php` - Provider-specific converters
- `/src/platform/src/Result/DeferredResult.php` - Lazy result conversion

## Key Patterns

### Pattern 1: ResultConverter Interface
- **Contract**:
  ```php
  interface ResultConverterInterface {
      public function supports(Model $model): bool;
      public function convert(RawResultInterface $result, array $options): ResultInterface;
      public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface;
  }
  ```
- **Type dispatch**: Like ModelClient
- **Conversion**: Raw → Typed result

### Pattern 2: DeferredResult (Lazy Evaluation)
- **Code**:
  ```php
  class DeferredResult {
      public function __construct(
          private RawResultInterface $rawResult,
          private Model $model,
          private iterable $resultConverters,
      ) {}

      public function asText(): string {
          return $this->getResult()->getText();
      }

      private function getResult(): ResultInterface {
          foreach ($this->resultConverters as $converter) {
              if ($converter->supports($this->model)) {
                  return $converter->convert($this->rawResult, $this->options);
              }
          }
          throw new RuntimeException('No converter');
      }
  }
  ```
- **Lazy**: Conversion only when accessed
- **Chainable**: Multiple result types from same raw

### Pattern 3: Result Type Hierarchy
```
ResultInterface
├── TextResult
├── StreamResult
├── ToolCallResult
├── ChoiceResult
├── BinaryResult (images/audio)
├── VectorResult (embeddings)
└── ObjectResult (structured output)
```

### Pattern 4: Provider-Specific Conversion
- **Anthropic**:
  ```php
  public function convert(RawHttpResult $result, array $options): ResultInterface {
      $data = $result->getData();

      // Error handling
      if (429 === $result->getObject()->getStatusCode()) {
          throw new RateLimitExceededException($retryAfter);
      }

      // Streaming detection
      if ($options['stream'] ?? false) {
          return new StreamResult($this->convertStream($result));
      }

      // Extract tool calls
      $toolCalls = [];
      foreach ($data['content'] as $content) {
          if ('tool_use' === $content['type']) {
              $toolCalls[] = new ToolCall($content['id'], $content['name'], $content['input']);
          }
      }

      // Return appropriate result type
      return [] !== $toolCalls
          ? new ToolCallResult(...$toolCalls)
          : new TextResult($data['content'][0]['text']);
  }
  ```

## Provider-Specific Handling

### Anthropic
- **Content Blocks**: Array of `{type, text|input}`
- **Tool Detection**: `type === 'tool_use'`
- **Text Extraction**: First text block
- **Usage**: Separate from content

### OpenAI
- **Choices**: Array of alternatives
- **Tool Calls**: In `message.tool_calls`
- **Finish Reason**: Determines result type
- **Usage**: In top-level `usage` field

## Notable Techniques

### 1. Token Usage Extractor
- **Separate interface**: `TokenUsageExtractorInterface`
- **Provider-specific**: Extracts from different locations
- **Integrated**: DeferredResult merges usage into result

### 2. Type Detection via Finish Reason
- **Pattern**: Map finish reason to result type
- **OpenAI**: `finish_reason === 'tool_calls'` → ToolCallResult
- **Anthropic**: `stop_reason === 'tool_use'` → ToolCallResult

### 3. Error Response Handling
- **HTTP codes**: 429, 503, etc. mapped to exceptions
- **Provider errors**: Extracted from response body
- **Early detection**: Before result conversion

## Architecture Insights

### Strengths
1. **Lazy evaluation**: Only convert when needed
2. **Type hierarchy**: Clean result types
3. **Provider isolation**: Converters independent

### Weaknesses
1. **Conversion overhead**: Extra step vs. direct parsing
2. **No validation**: Result structure not validated
3. **Limited caching**: Re-converts on each access
