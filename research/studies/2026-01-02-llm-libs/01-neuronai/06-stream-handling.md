# NeuronAI - Stream Handling

## Core Files
- `/src/Providers/OpenAI/HandleStream.php` - SSE parsing + tool call accumulation (200+ lines)
- `/src/Providers/Anthropic/HandleStream.php` - Event-based streaming with tool reconstruction
- `/src/HandleStream.php` - Agent-level stream orchestration (trait)

## Key Patterns

### Pattern 1: Generator-Based Streaming
- **Location**: All HandleStream traits
- **Mechanism**: `Generator` yields text chunks + metadata + tool calls
- **Code**:
  ```php
  public function stream(array|string $messages, callable $executeToolsCallback): Generator {
      $stream = $this->client->post('chat/completions', [
          'stream' => true,
          RequestOptions::JSON => $json
      ])->getBody();

      while (! $stream->eof()) {
          $line = $this->parseNextDataLine($stream);
          // Process chunk
          yield $content;  // Text chunk
          yield $metadata; // Usage/tool info
      }
  }
  ```
- **Yields**: String chunks, JSON-encoded metadata, or `ToolCallMessage` objects
- **Callback**: `$executeToolsCallback` invoked when tools detected

### Pattern 2: SSE Line Parsing
- **OpenAI Format**: `data: {"delta": {...}}\n\n`
- **Code**:
  ```php
  protected function parseNextDataLine(StreamInterface $stream): ?array {
      $line = $this->readLine($stream);
      if (!str_starts_with($line, 'data:')) {
          return null;
      }
      $data = trim(substr($line, 5));  // Remove 'data:' prefix
      if ($data === '[DONE]') {
          return null;
      }
      return json_decode($data, true);
  }
  ```
- **Filters**: Only lines starting with `data:`
- **Terminates**: On `[DONE]` marker

### Pattern 3: Tool Call Accumulation Across Chunks
- **Problem**: Tool calls arrive fragmented across multiple SSE events
- **Solution**: Accumulate in mutable array indexed by tool call index
- **Code**:
  ```php
  protected function composeToolCalls(array $line, array $toolCalls): array {
      foreach ($line['choices'][0]['delta']['tool_calls'] as $call) {
          $index = $call['index'];
          if (!array_key_exists($index, $toolCalls)) {
              // First chunk: initialize
              $toolCalls[$index] = [
                  'id' => $call['id'] ?? '',
                  'function' => [
                      'name' => $call['function']['name'] ?? '',
                      'arguments' => $call['function']['arguments'] ?? '',
                  ]
              ];
          } else {
              // Subsequent chunks: append arguments
              $toolCalls[$index]['function']['arguments'] .= $call['function']['arguments'] ?? '';
          }
      }
      return $toolCalls;
  }
  ```
- **Indexed**: By `$call['index']` (provider-assigned)
- **Concatenates**: `arguments` field across chunks (JSON string fragments)

### Pattern 4: Goto for Tool Call Completion
- **Location**: OpenAI HandleStream.php:87-96
- **Code**:
  ```php
  if ($this->finishForToolCall($choice)) {
      goto finish;
  }
  // Later...
  if ($this->finishForToolCall($choice)) {
      finish:
      yield from $executeToolsCallback(
          $this->createToolCallMessage([...])
      );
      return;
  }
  ```
- **Why**: Jump to tool execution from two locations
- **Controversial**: `goto` considered harmful, but used for control flow

### Pattern 5: Usage Metadata Streaming
- **OpenAI**: Final event contains usage
  ```php
  if (!empty($line['usage'])) {
      yield json_encode(['usage' => [
          'input_tokens' => $line['usage']['prompt_tokens'],
          'output_tokens' => $line['usage']['completion_tokens'],
      ]]);
  }
  ```
- **Anthropic**: Multiple events (message_start, message_delta)
  - `message_start`: Initial usage estimate
  - `message_delta`: Final usage
- **Format**: JSON-encoded string (not object)

## Provider-Specific Handling

### OpenAI Streaming
- **Event Format**: SSE with `data:` prefix
- **Chunk Structure**:
  ```json
  {
    "choices": [{
      "delta": {
        "content": "text chunk",
        "tool_calls": [{"index": 0, "function": {"name": "...", "arguments": "..."}}]
      },
      "finish_reason": null | "stop" | "tool_calls"
    }],
    "usage": {"prompt_tokens": 100, "completion_tokens": 50}  // Final event only
  }
  ```
- **Tool Calls**: Indexed array, arguments concatenated across chunks
- **Finish Detection**: `finish_reason === 'tool_calls'`

### Anthropic Streaming
- **Event Format**: SSE with event types
- **Event Types**:
  - `message_start` - Initial message + usage estimate
  - `content_block_start` - New content block (text or tool_use)
  - `content_block_delta` - Chunk within block
  - `content_block_stop` - Block complete
  - `message_delta` - Message metadata update (usage)
  - `message_stop` - Stream complete
- **Tool Calls**: `content_block` with `type: 'tool_use'`
  - Separate events for `name`, `input` (as JSON delta)
  - Reconstruct full tool input from deltas
- **Text + Tools**: Can interleave text and tool_use blocks

## Notable Techniques

### 1. Text Accumulation
- **Pattern**: `$text .= $content;`
- **Why**: Build full message incrementally for history
- **Location**: Throughout stream loops

### 2. Reasoning Content (OpenAI o1)
- **Field**: `delta.reasoning_content`
- **Separate** from regular content
- **Accumulated**: In `$reasoning` variable
- **Use**: For models with explicit reasoning steps (o1, o3)

### 3. Callback Execution in Stream
- **Pattern**: `yield from $executeToolsCallback(...)`
- **Why**: Delegate to caller for tool execution
- **Recursive**: Callback may stream recursively
- **Location**: When tool calls detected

### 4. Line Buffering
- **Problem**: SSE events may not align with stream reads
- **Solution**: Buffer partial lines, accumulate until `\n`
- **Code**:
  ```php
  protected function readLine(StreamInterface $stream): string {
      $buffer = '';
      while (!$stream->eof()) {
          $buffer .= $stream->read(1);
          if (str_ends_with($buffer, "\n")) {
              return rtrim($buffer);
          }
      }
      return $buffer;
  }
  ```
- **Byte-by-byte**: Read 1 byte at a time until newline

### 5. Anthropic Tool Input Reconstruction
- **Challenge**: Tool input arrives as JSON string fragments (`input_json_delta`)
- **Solution**: Concatenate deltas, then `json_decode()` once complete
- **Code**:
  ```php
  // Accumulate
  $toolCalls[$index]['input'] .= $line['delta']['input_json_delta'] ?? '';

  // After content_block_stop
  $toolCalls = array_map(function (array $call) {
      $call['input'] = json_decode((string) $call['input'], true);
      return $call;
  }, $toolCalls);
  ```

## Limitations/Edge Cases

### 1. No Partial Object Yielding
- Yields strings or complete `ToolCallMessage`
- Cannot yield partial structured output
- Client must buffer full response for deserialization

### 2. Tool Arguments as Concatenated String
- Fragments may not be valid JSON independently
- Cannot validate until complete
- Risk of malformed JSON from LLM

### 3. No Stream Error Handling
- HTTP errors during stream not caught
- Connection drops silently fail
- No retry on stream interruption

### 4. Blocking I/O
- `while (!$stream->eof())` blocks thread
- No async/await
- Cannot cancel stream externally

### 5. Memory Accumulation
- Full text accumulated in memory
- Tool calls accumulated in array
- Long streams may exhaust memory

### 6. No Timeout Handling
- Stream may hang indefinitely
- No read timeout configured
- Must rely on HTTP client timeout

### 7. Usage Data Format Inconsistency
- Yields JSON string `{"usage": {...}}`
- Not typed object like other responses
- Client must parse JSON again

## Architecture Insights

### Strengths
1. **Generator-based**: Lazy evaluation, backpressure support
2. **Tool integration**: Seamless tool call execution mid-stream
3. **Provider flexibility**: Different SSE formats abstracted
4. **Metadata streaming**: Usage info available before completion

### Weaknesses
1. **Blocking**: Synchronous I/O
2. **No cancellation**: Cannot stop stream externally
3. **Memory growth**: Unbounded accumulation
4. **Error handling**: No stream-specific error recovery

### Comparison
- **vs. EventSource**: Custom SSE parsing vs. standard
- **vs. Async generators**: Sync generator vs. async
- **vs. Observable**: Generator vs. reactive streams
- **vs. Instructor**: Similar callback pattern for tools
