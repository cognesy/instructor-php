# Symfony AI - Stream Handling

## Core Files
- `/src/platform/src/Result/StreamResult.php` - Stream result wrapper
- `/src/platform/src/Bridge/*/ResultConverter.php` - `convertStream()` methods
- Symfony EventSourceHttpClient

## Key Patterns

### Pattern 1: Generator-Based Streaming
- **Code**:
  ```php
  class StreamResult {
      public function __construct(private readonly \Generator $generator) {}

      public function getContent(): \Generator {
          yield from $this->generator;
      }
  }
  ```
- **Lazy**: Generator not consumed until accessed
- **Simple**: Just wraps Generator

### Pattern 2: EventSourceHttpClient Integration
- **Symfony**: Built-in SSE support
- **Auto-parsing**: Handles SSE format
- **Code**:
  ```php
  foreach ($result->getDataStream() as $data) {
      if ('content_block_delta' === $data['type']) {
          yield $data['delta']['text'];
      }
  }
  ```

### Pattern 3: Provider-Specific Stream Conversion
- **Anthropic**:
  ```php
  private function convertStream(RawResultInterface $result): \Generator {
      foreach ($result->getDataStream() as $data) {
          if ('content_block_delta' !== $data['type']) continue;
          if (!isset($data['delta']['text'])) continue;
          yield $data['delta']['text'];
      }
  }
  ```
- **Filters**: Only relevant events
- **Extracts**: Text content only

## Provider-Specific Handling

### Anthropic
- **Events**: `message_start`, `content_block_delta`, etc.
- **Filter**: Only delta events with text
- **Simple**: Yields text strings

### OpenAI
- **Events**: Different structure
- **Extraction**: `delta.content` field
- **Tool calls**: Separate handling

## Architecture Insights

### Strengths
1. **EventSource**: Native SSE support
2. **Simple**: Minimal abstraction
3. **Lazy**: Generator-based

### Weaknesses
1. **Limited**: Only yields text
2. **No events**: No tool call streaming
3. **No state**: No accumulation
