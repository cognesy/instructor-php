# InstructorPHP Polyglot - Stream Handling

## Core Files
- `/src/Inference/Streaming/InferenceStream.php` - High-level stream wrapper
- `/src/Inference/Streaming/EventStreamReader.php` - SSE parsing
- `/src/Inference/Data/PartialInferenceResponse.php` - Streaming delta model
- `/src/Inference/Drivers/*/ResponseAdapter.php` - Provider-specific parsing

## Key Patterns

### Pattern 1: InferenceStream Wrapper
- **File**: `/src/Inference/Streaming/InferenceStream.php`
- **Structure**:
  ```php
  class InferenceStream {
      protected iterable $stream;         // Raw partial responses
      protected InferenceExecution $execution; // Accumulates state

      public function responses(): Generator {
          foreach ($this->makePartialResponses($this->stream) as $partialResponse) {
              yield $partialResponse;
          }
      }

      public function final(): ?InferenceResponse {
          // Drain stream to get final accumulated response
          foreach ($this->makePartialResponses($this->stream) as $_) {}
          return $this->execution->response();
      }
  }
  ```
- **Features**: `map()`, `reduce()`, `filter()`, `all()`
- **Lazy**: Generator-based, doesn't consume stream until accessed

### Pattern 2: PartialInferenceResponse Accumulation
- **File**: `/src/Inference/Data/PartialInferenceResponse.php`
- **Code**:
  ```php
  class PartialInferenceResponse {
      public readonly string $contentDelta;
      public readonly string $reasoningContentDelta;
      public readonly string $toolId;
      public readonly string $toolName;
      public readonly string $toolArgs;

      private string $content;           // Accumulated
      private string $reasoningContent;  // Accumulated
      private array $tools = [];         // Accumulated tool calls

      public function withAccumulatedContent(PartialInferenceResponse $previous): self {
          $baseContent = $previous->content() !== ''
              ? $previous->content()
              : ($previous->contentDelta ?? '');
          $this->content = $baseContent . ($this->contentDelta ?? '');

          // Accumulate tools
          $this->tools = $previous->tools;
          if ($this->hasToolName()) {
              $this->accumulateTool();
          }

          return $this;
      }
  }
  ```
- **Deltas**: Each chunk has delta fields
- **Accumulated**: Full content built up across chunks
- **Tool tracking**: Internal array of accumulated tool calls

### Pattern 3: EventStreamReader (SSE Parsing)
- **File**: `/src/Inference/Streaming/EventStreamReader.php`
- **Code**:
  ```php
  class EventStreamReader {
      protected function readLines(iterable $stream): Generator {
          $buffer = '';
          foreach ($stream as $chunk) {
              $buffer .= $chunk;
              while (false !== ($pos = strpos($buffer, "\n"))) {
                  yield substr($buffer, 0, $pos + 1);
                  $buffer = substr($buffer, $pos + 1);
              }
          }
          if ($buffer !== '') {
              yield $buffer;
          }
      }

      protected function processLine(string $line): ?string {
          $line = trim($line);
          if ($line === '') {
              return null;
          }
          $data = $this->parse($line);
          if ($data === false || $data === true) {
              return null;
          }
          return $data;
      }
  }
  ```
- **Buffering**: Accumulates incomplete lines
- **Parser**: Custom closure for provider-specific parsing
- **Events**: Dispatches `StreamEventReceived`, `StreamEventParsed`

### Pattern 4: Provider-Specific SSE Parsing
- **OpenAI**:
  ```php
  public function toEventBody(string $data): string|bool {
      if (!str_starts_with($data, 'data:')) {
          return '';
      }
      $data = trim(substr($data, 5));
      return match(true) {
          $data === '[DONE]' => false,
          default => $data,
      };
  }
  ```
- **Anthropic**:
  ```php
  public function toEventBody(string $data): string|bool {
      if (!str_starts_with($data, 'data:')) {
          return '';
      }
      $data = trim(substr($data, 5));
      return $data;
  }
  ```
- **Returns**: `false` to terminate, empty string to skip, or JSON string

## Streaming Flow

### Full Pipeline
1. **HTTP Stream**: Raw byte stream from HTTP client
2. **EventStreamReader**: Buffers and parses SSE lines
3. **ResponseAdapter**: `fromStreamResponse()` creates `PartialInferenceResponse`
4. **InferenceStream**: Accumulates via `withAccumulatedContent()`
5. **Generator**: Yields enriched partial responses
6. **InferenceExecution**: Tracks final accumulated state

### Accumulation Logic
- **Code**:
  ```php
  private function makePartialResponses(iterable $stream): Generator {
      $priorResponse = PartialInferenceResponse::empty();
      foreach ($stream as $partialResponse) {
          if ($partialResponse === null) {
              continue;
          }
          // Enrich with accumulated content
          $partialResponse = $partialResponse->withAccumulatedContent($priorResponse);
          $this->notifyOnPartialResponse($partialResponse);
          yield $partialResponse;
          $priorResponse = $partialResponse;
      }
      $this->finalizeStream();
  }
  ```
- **Each chunk**: Gets previous chunk's accumulated state
- **Tool calls**: Accumulated across chunks by tool ID or name
- **Usage**: Can be cumulative or per-chunk (provider-specific)

## Notable Techniques

### 1. Functional Stream Operations
- **map()**: Transform each partial response
- **reduce()**: Aggregate stream to single value
- **filter()**: Select specific chunks
- **all()**: Collect all partials into array

### 2. Lazy Final Response
- **Pattern**: `final()` drains stream if not consumed
- **Code**:
  ```php
  public function final(): ?InferenceResponse {
      if (!$this->execution->isFinalized()) {
          foreach ($this->makePartialResponses($this->stream) as $_) {}
      }
      return $this->execution->response();
  }
  ```
- **Ensures**: Final response even if caller stops early

### 3. InferenceExecution State Tracker
- **Immutable updates**: New execution object per partial
- **Tracks**: All partials, final response, finalized flag
- **Converts**: Last partial → full `InferenceResponse`

### 4. Tool Call Reconstruction
- **Internal array**: `$tools` stores JSON args by tool key
- **Keys**: `"id:<toolId>"` or synthetic `"name:<toolName>#<n>"`
- **Lazy conversion**: `toolCalls()` materializes `ToolCalls` on access
- **Avoids**: Creating thousands of ToolCall objects mid-stream

### 5. Event-Driven Processing
- **Events**: `StreamEventReceived`, `StreamEventParsed`, `PartialInferenceResponseCreated`
- **Callback**: `onPartialResponse(callable $callback)`
- **Use case**: Real-time UI updates, logging, metrics

## Provider-Specific Handling

### OpenAI
- **Event format**: `data: {...}\n\ndata: [DONE]\n\n`
- **Termination**: `[DONE]` marker
- **Delta fields**: `delta.content`, `delta.tool_calls`
- **Usage**: Final chunk only (cumulative: false)

### Anthropic
- **Event format**: `data: {...}\n\n`
- **Event types**: `message_start`, `content_block_delta`, `message_delta`, `message_stop`
- **Filtering**: Only process relevant event types
- **Usage**: Cumulative across chunks

## Architecture Insights

### Strengths
1. **Functional operations**: map/reduce/filter for flexibility
2. **Lazy evaluation**: Generator-based, memory efficient
3. **Accumulated state**: Each partial has full content
4. **Event-driven**: Rich observability
5. **Tool reconstruction**: Efficient memory usage

### Weaknesses
1. **Manual accumulation**: Client must call `withAccumulatedContent()`
2. **Mutable partials**: Internal state mutated for accumulation
3. **No backpressure**: Can't throttle upstream
4. **Complex state**: Multiple layers (EventStreamReader, InferenceStream, InferenceExecution)

## Comparison to Other Libraries

### Different Approaches
- **NeuronAI**: Trait-based streaming, recursive tool execution
- **Prism**: Event-based with `StreamState`, explicit event types
- **Symfony AI**: Simple Generator wrapper, minimal state
- **InstructorPHP Polyglot**: Functional stream operations, layered architecture

### Trade-offs
- **Pro**: Functional operations (map/reduce/filter)
- **Con**: More complex than simple Generator
- **Pro**: Rich accumulated state in each partial
- **Con**: More memory per partial response
- **Pro**: Event-driven for observability
- **Con**: Many event types to handle
