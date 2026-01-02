# InstructorPHP Polyglot - Response Extraction

## Core Files
- `/src/Inference/Drivers/OpenAI/OpenAIResponseAdapter.php` - Response parsing
- `/src/Inference/Drivers/Anthropic/AnthropicResponseAdapter.php` - Anthropic parsing
- `/src/Inference/Data/InferenceResponse.php` - Response data model
- `/src/Inference/Data/PartialInferenceResponse.php` - Streaming chunks

## Key Patterns

### Pattern 1: ResponseAdapter Interface
- **Contract**:
  ```php
  interface CanTranslateInferenceResponse {
      public function fromResponse(HttpResponse $response): ?InferenceResponse;
      public function fromStreamResponse(string $eventBody, ?HttpResponse $responseData): ?PartialInferenceResponse;
      public function toEventBody(string $data): string|bool;
  }
  ```
- **Two modes**: Full response vs. streaming
- **SSE handling**: `toEventBody()` parses SSE format

### Pattern 2: InferenceResponse Data Model
- **Structure**:
  ```php
  class InferenceResponse {
      public string $content;
      public string $finishReason;
      public ToolCalls $toolCalls;
      public Usage $usage;
      public HttpResponse $responseData;
  }
  ```
- **Complete**: Single response
- **Mutable**: Can be modified

### Pattern 3: Streaming Response
- **PartialInferenceResponse**:
  ```php
  class PartialInferenceResponse {
      public string $contentDelta;
      public string $toolId;
      public string $toolName;
      public string $toolArgs;
      public string $finishReason;
      public Usage $usage;
      public bool $usageIsCumulative;
  }
  ```
- **Deltas**: Incremental updates
- **Accumulation**: Client must accumulate

### Pattern 4: Provider-Specific Parsing
- **OpenAI**:
  ```php
  public function fromResponse(HttpResponse $response): ?InferenceResponse {
      $data = json_decode($response->body(), true);
      return new InferenceResponse(
          content: $this->makeContent($data),
          finishReason: $data['choices'][0]['finish_reason'] ?? '',
          toolCalls: $this->makeToolCalls($data),
          usage: $this->usageFormat->fromData($data),
          responseData: $response,
      );
  }

  protected function makeContent(array $data): string {
      $contentMsg = $data['choices'][0]['message']['content'] ?? '';
      $contentFnArgs = $data['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
      return match(true) {
          !empty($contentMsg) => $contentMsg,
          !empty($contentFnArgs) => $contentFnArgs,
          default => ''
      };
  }
  ```

## Provider-Specific Handling

### OpenAI
- **Path**: `choices[0].message.content`
- **Tool calls**: `choices[0].message.tool_calls[]`
- **Finish**: `choices[0].finish_reason`
- **Usage**: Top-level `usage`

### Anthropic
- **Path**: Iterate content blocks
- **Tool calls**: Blocks with `type: 'tool_use'`
- **Finish**: `stop_reason`
- **Usage**: Top-level `usage`

## Notable Techniques

### 1. SSE Parsing
- **Code**:
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
- **Filters**: `data:` prefix
- **Terminates**: On `[DONE]`

### 2. Content Fallback
- **Pattern**: Try message content, then tool args
- **Why**: OpenAI may only have tool args
- **Match**: Type-safe selection

### 3. ToolCalls Collection
- **Type**: `ToolCalls` collection class
- **Factory**: `ToolCalls::fromArray()`
- **Benefits**: Type-safe, iterable

### 4. Usage Format Delegation
- **Pattern**: Separate `UsageFormat` class
- **Extracts**: Token counts from data
- **Provider-specific**: Different field names

## Architecture Insights

### Strengths
1. **Adapter pattern**: Clean extraction
2. **Streaming support**: Separate partial type
3. **Type-safe**: Value objects

### Weaknesses
1. **Manual parsing**: No validation
2. **Null handling**: Limited null checks
3. **Streaming accumulation**: Manual client-side
