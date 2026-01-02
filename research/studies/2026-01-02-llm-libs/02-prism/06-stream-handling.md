# Prism - Stream Handling

## Core Files
- `/src/Providers/OpenAI/Handlers/Stream.php` - OpenAI streaming
- `/src/Providers/Anthropic/Handlers/Stream.php` - Anthropic streaming
- `/src/Streaming/StreamState.php` - State tracking
- `/src/Streaming/Events/*` - Event types
- `/src/Streaming/Adapters/*` - Output format adapters

## Key Patterns

### Pattern 1: Event-Based Streaming
- **Events**: StreamStartEvent, TextDeltaEvent, ToolCallEvent, StreamEndEvent, etc.
- **Generator**: Yields event objects (not strings)
- **Code**:
  ```php
  public function handle(Request $request): Generator {
      $response = $this->sendRequest($request);
      yield from $this->processStream($response, $request);
  }

  protected function processStream(Response $response, Request $request): Generator {
      while (!$response->getBody()->eof()) {
          $event = $this->parseNextSSEEvent($response->getBody());
          if ($streamEvent = $this->processEvent($event)) {
              yield $streamEvent;  // Typed event object
          }
      }
  }
  ```

### Pattern 2: StreamState Management
- **Tracks**: Current text, tool calls, citations, usage, finish reason
- **Mutable**: Updated as events arrive
- **Queries**: `shouldEmitStreamStart()`, `shouldEmitTextStart()`, `hasToolCalls()`
- **Code**:
  ```php
  class StreamState {
      protected string $currentText = '';
      protected array $toolCalls = [];
      protected ?Usage $usage = null;
      protected ?FinishReason $finishReason = null;

      public function appendText(string $content): self {
          $this->currentText .= $content;
          return $this;
      }

      public function shouldEmitTextStart(): bool {
          return !$this->textStarted && $this->currentText !== '';
      }
  }
  ```

### Pattern 3: Stream Adapters
- **SSEAdapter**: Server-Sent Events format
- **DataProtocolAdapter**: Newline-delimited JSON
- **BroadcastAdapter**: Laravel Broadcasting (WebSockets)
- **Code**:
  ```php
  class SSEAdapter {
      public function __invoke(Generator $stream, PendingRequest $request, ?callable $callback): StreamedResponse {
          return response()->stream(function () use ($stream, $callback) {
              foreach ($stream as $event) {
                  echo "event: " . class_basename($event) . "\n";
                  echo "data: " . json_encode($event) . "\n\n";
                  flush();
                  if ($callback) $callback($request, $events);
              }
          });
      }
  }
  ```

## Provider-Specific Handling

### OpenAI
- **Format**: SSE with multiple event types
- **Events**: `response.created`, `response.output_text.delta`, `response.completed`
- **State tracking**: OpenAI-specific field mappings

### Anthropic
- **Format**: SSE with event types
- **Events**: `message_start`, `content_block_delta`, `message_stop`
- **Tool input**: Arrives as `input_json_delta` (must concatenate)

## Notable Techniques

### 1. Event Type Hierarchy
- Base `StreamEvent` class
- Specific events: `TextDeltaEvent`, `ThinkingEvent`, `CitationEvent`
- Type-safe handling in consumers

### 2. Callback Integration
- Optional callback after each event
- Can collect/process events
- Access to full event collection

### 3. Laravel Response Integration
- `StreamedResponse` for HTTP streaming
- Works with Laravel's response system
- Auto-handles chunked encoding

### 4. Provider Tool Events
- Separate from Prism tools
- Preserves native provider data
- Allows provider-specific logic

## Architecture Insights

### Strengths
1. **Event-driven**: Clean separation of concerns
2. **Adapters**: Multiple output formats
3. **State tracking**: Explicit state management
4. **Type-safe**: Events are objects, not strings
5. **Laravel integration**: Native framework support

### Weaknesses
1. **Complexity**: Many event types to handle
2. **Memory**: State accumulates in memory
3. **Laravel-specific**: Adapters tied to framework
