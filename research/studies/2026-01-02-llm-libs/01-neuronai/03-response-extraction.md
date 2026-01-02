# NeuronAI - Response Extraction

## Core Files
- `/src/Providers/OpenAI/HandleChat.php` - OpenAI response parsing (trait, lines 44-62)
- `/src/Providers/Anthropic/HandleChat.php` - Anthropic response parsing (trait, lines 43-66)
- `/src/Providers/OpenAI/OpenAI.php` - Tool call extraction (lines 89-109)
- `/src/Providers/Anthropic/Anthropic.php` - Tool call extraction (lines 90-100)
- `/src/Chat/Messages/Usage.php` - Token usage value object
- `/src/Chat/Messages/ToolCallMessage.php` - Tool call response type

## Key Patterns

### Pattern 1: Inline Response Parsing in Promise
- **Location**: `->then()` callback in `chatAsync()` methods
- **Mechanism**: JSON decoding + immediate type detection + object construction
- **Code** (OpenAI):
  ```php
  return $this->client->postAsync('chat/completions', [RequestOptions::JSON => $json])
      ->then(function (ResponseInterface $response) {
          $result = json_decode($response->getBody()->getContents(), true);

          if ($result['choices'][0]['finish_reason'] === 'tool_calls') {
              $response = $this->createToolCallMessage($result['choices'][0]['message']);
          } else {
              $response = $this->createAssistantMessage($result);
          }

          if (array_key_exists('usage', $result)) {
              $response->setUsage(
                  new Usage($result['usage']['prompt_tokens'], $result['usage']['completion_tokens'])
              );
          }

          return $response;
      });
  ```
- **No validation**: JSON decoded without error handling
- **Immediate mutation**: Usage attached to response object

### Pattern 2: Finish Reason Detection
- **OpenAI**: Checks `finish_reason === 'tool_calls'`
  - Located at: `$result['choices'][0]['finish_reason']`
  - Binary decision: tool call vs. regular message
- **Anthropic**: Checks `type === 'tool_use'`
  - Located at: Last content block `end($result['content'])['type']`
  - Binary decision: tool use vs. text message

### Pattern 3: Tool Call Reconstruction
- **OpenAI** (lines 89-109):
  ```php
  protected function createToolCallMessage(array $message): ToolCallMessage {
      $tools = array_map(
          fn (array $item): ToolInterface => $this->findTool($item['function']['name'])
              ->setInputs(json_decode((string) $item['function']['arguments'], true))
              ->setCallId($item['id']),
          $message['tool_calls']  // Array of tool calls
      );

      $content = $message['content'] ?? '';
      $result = new ToolCallMessage($content, $tools);
      $result->addMetadata('tool_calls', $message['tool_calls']); // Preserve raw data

      return $result;
  }
  ```
  - **Multiple tools**: OpenAI can return multiple tool calls in one response
  - **JSON parsing**: Arguments are JSON string, must decode
  - **Tool lookup**: `findTool()` retrieves registered tool by name
  - **Metadata preservation**: Raw tool_calls stored in metadata

- **Anthropic** (lines 90-100):
  ```php
  public function createToolCallMessage(array $message, string $text = ''): Message {
      $tool = $this->findTool($message['name'])
          ->setInputs($message['input'])    // Already parsed array
          ->setCallId($message['id']);

      return new ToolCallMessage(
          $text,                             // Accumulated text from stream
          [$tool]                            // Single tool in array
      );
  }
  ```
  - **Single tool**: Anthropic returns one tool per content block
  - **No JSON parsing**: Input already parsed by provider
  - **Text parameter**: Supports text + tool use in same response

### Pattern 4: Usage Extraction
- **OpenAI** (HandleChat.php:54-58):
  ```php
  if (array_key_exists('usage', $result)) {
      $response->setUsage(
          new Usage($result['usage']['prompt_tokens'], $result['usage']['completion_tokens'])
      );
  }
  ```
  - Fields: `prompt_tokens`, `completion_tokens`
  - Simple two-field VO

- **Anthropic** (HandleChat.php:55-62):
  ```php
  if (array_key_exists('usage', $result)) {
      $response->setUsage(
          new Usage(
              $result['usage']['input_tokens'],
              $result['usage']['output_tokens']
          )
      );
  }
  ```
  - Fields: `input_tokens`, `output_tokens`
  - Same VO, different field names

## Provider-Specific Handling

### OpenAI Response Structure
```json
{
  "choices": [
    {
      "finish_reason": "stop" | "tool_calls" | "length",
      "message": {
        "content": "text response" | null,
        "tool_calls": [
          {
            "id": "call_abc123",
            "function": {
              "name": "function_name",
              "arguments": "{\"key\":\"value\"}"  // JSON string!
            }
          }
        ]
      }
    }
  ],
  "usage": {
    "prompt_tokens": 100,
    "completion_tokens": 50
  }
}
```

**Extraction Path**:
- Content: `['choices'][0]['message']['content']`
- Finish reason: `['choices'][0]['finish_reason']`
- Tool calls: `['choices'][0]['message']['tool_calls']`
- Usage: `['usage']`

**Key Differences**:
- Tool arguments as **JSON string** (must decode)
- Multiple tool calls in single response
- Content often null when tool_calls present

### Anthropic Response Structure
```json
{
  "content": [
    {"type": "text", "text": "some text"},
    {"type": "tool_use", "id": "toolu_123", "name": "tool_name", "input": {"key": "value"}}
  ],
  "usage": {
    "input_tokens": 100,
    "output_tokens": 50
  }
}
```

**Extraction Path**:
- Last content block: `end($result['content'])`
- Type detection: `$content['type']`
- Text: `$content['text']`
- Tool: `$content['name']`, `$content['input']`, `$content['id']`
- Usage: `['usage']`

**Key Differences**:
- Tool input as **parsed array** (already decoded)
- One tool per content block
- Text + tool_use can coexist in content array
- Uses `end()` to get last block (assumes tool_use is last)

## Notable Techniques

### 1. Direct Array Access Without Validation
- **Pattern**: `$result['choices'][0]['message']['content']`
- **Risk**: No null checks, will fatal error if structure differs
- **Location**: Throughout both HandleChat traits

### 2. Tool Instance Mutation
- **Pattern**: `$this->findTool($name)->setInputs(...)->setCallId(...)`
- **Why**: Tools are registered instances, cloned and mutated
- **Implication**: Tool must be registered before response can be parsed

### 3. Metadata Preservation (OpenAI only)
- **Code**: `$result->addMetadata('tool_calls', $message['tool_calls'])`
- **Why**: Raw provider data available for debugging/custom logic
- **Location**: OpenAI.php:106

### 4. Content Fallback
- **OpenAI**: `$message['content'] ?? ''`
- **Anthropic**: Assumes text exists if not tool_use
- **Reason**: OpenAI returns null content for tool calls

### 5. `end()` for Last Element
- **Anthropic**: `$content = end($result['content'])`
- **Assumption**: Tool_use always last in content array
- **Risk**: If Anthropic changes order, extraction fails

## Limitations/Edge Cases

### 1. No Error Handling
- JSON decode errors not caught
- Missing fields cause fatal errors
- No provider error message extraction

### 2. Hard-Coded Array Paths
- Assumes fixed response structure
- Provider API changes break code immediately
- No schema validation

### 3. Finish Reason Ignored (Anthropic)
- Anthropic returns `stop_reason` field
- Not extracted or checked
- Could be: `end_turn`, `max_tokens`, `stop_sequence`

### 4. Multi-Tool Handling Difference
- OpenAI: Multiple tools in one response → `ToolCallMessage` with array
- Anthropic: One tool at a time → Must call recursively
- Different execution models

### 5. Tool Not Found Exception
- `findTool()` throws if tool name not registered
- Provider suggests tool that wasn't provided
- No graceful degradation

### 6. Usage Field Optional
- Both providers check `array_key_exists('usage')`
- Some responses may not include usage
- Message object allows null usage

### 7. Token Field Name Mismatch
- OpenAI: `prompt_tokens`, `completion_tokens`
- Anthropic: `input_tokens`, `output_tokens`
- Same concept, different names
- Usage VO constructor params must match

### 8. No Streaming Differentiation
- Same extraction logic for streaming vs. non-streaming
- Streaming has different response structure
- Handled in separate `HandleStream` trait

## Architecture Insights

### Strengths
1. **Simple extraction**: Direct array access, minimal abstraction
2. **Type detection**: Uses finish_reason/type for routing
3. **Unified message types**: Both providers return `Message` subclasses
4. **Metadata support**: Can preserve provider-specific data

### Weaknesses
1. **Brittle parsing**: No validation, assumes structure
2. **Tight coupling**: Response structure knowledge in trait
3. **Error-prone**: Missing null checks
4. **Not extensible**: Hard to add new response types

### Comparison to Typical Approaches
- **vs. DTO mapping**: No intermediate DTOs, direct to domain objects
- **vs. Deserializer**: No serializer component, manual extraction
- **vs. Response objects**: Returns domain Messages, not response wrappers
- **vs. Null safety**: Assumes non-null, fails fast

## Response Flow
1. HTTP response received in promise callback
2. `json_decode()` to associative array
3. Finish reason / type detection
4. Branch to `createToolCallMessage()` or `createAssistantMessage()`
5. Tool lookup + input parsing
6. Usage extraction (if present)
7. Return `Message` subclass instance
