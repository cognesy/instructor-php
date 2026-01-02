# Prism - Response Extraction

## Core Files
- `/src/Text/Response.php` - Normalized response (readonly)
- `/src/Text/Step.php` - Single conversation turn
- `/src/Text/ResponseBuilder.php` - Multi-step aggregation
- `/src/Providers/OpenAI/Handlers/Text.php` - Response parsing
- `/src/Providers/Anthropic/Handlers/Text.php` - Response parsing

## Key Patterns

### Pattern 1: Multi-Step Response Model
- **Structure**:
  ```php
  readonly class Response {
      public Collection $steps;        // Step[] - all turns
      public string $text;             // Final text
      public FinishReason $finishReason;
      public array $toolCalls;         // Final tool calls
      public array $toolResults;       // Execution results
      public Usage $usage;             // Aggregated tokens
      public Meta $meta;               // IDs, rate limits
      public Collection $messages;     // Full conversation
      public array $additionalContent; // Citations, thinking
  }
  ```
- **Benefits**: Complete conversation history, not just final turn

### Pattern 2: ResponseBuilder Aggregation
- **Mechanism**: Builds response from multiple steps
- **Code**:
  ```php
  class ResponseBuilder {
      public Collection $steps;

      public function toResponse(): Response {
          $finalStep = $this->steps->last();
          return new Response(
              steps: $this->steps,
              text: $finalStep->text,
              finishReason: $finalStep->finishReason,
              toolCalls: $finalStep->toolCalls,
              toolResults: $finalStep->toolResults,
              usage: $this->calculateTotalUsage(),  // Sums all steps
              meta: $finalStep->meta,
              messages: collect($finalStep->messages),
              additionalContent: $finalStep->additionalContent,
          );
      }

      protected function calculateTotalUsage(): Usage {
          return new Usage(
              promptTokens: $this->steps->sum('usage.promptTokens'),
              completionTokens: $this->steps->sum('usage.completionTokens'),
              cacheWriteInputTokens: $this->steps->sum('usage.cacheWriteInputTokens'),
              cacheReadInputTokens: $this->steps->sum('usage.cacheReadInputTokens'),
          );
      }
  }
  ```

### Pattern 3: Provider-Specific Extraction
- **OpenAI** (`Handlers/Text.php`):
  ```php
  $data = $response->json();
  $text = data_get($data, 'output.{last}.content.0.text') ?? '';
  $toolCalls = ToolCallMap::map(
      array_filter($data['output'], fn($o) => $o['type'] === 'function_call')
  );
  $citations = $this->extractCitations($data);

  $responseMessage = new AssistantMessage(
      content: $text,
      toolCalls: $toolCalls,
      additionalContent: [
          'citations' => $citations,
          'provider_tool_calls' => ProviderToolCallMap::map($data['output']),
      ]
  );
  ```
- **Anthropic** (`Handlers/Text.php`):
  ```php
  $text = $this->extractText($data);          // From content blocks
  $toolCalls = $this->extractToolCalls($data); // tool_use blocks
  $thinking = $this->extractThinking($data);   // If enabled

  $this->tempResponse = new Response(
      text: $text,
      toolCalls: $toolCalls,
      usage: new Usage(
          promptTokens: $data['usage']['input_tokens'],
          completionTokens: $data['usage']['output_tokens'],
          cacheWriteInputTokens: $data['usage']['cache_creation_input_tokens'],
          cacheReadInputTokens: $data['usage']['cache_read_input_tokens'],
      ),
      additionalContent: [
          'thinking' => $thinking['thinking'] ?? null,
          'thinking_signature' => $thinking['thinking_signature'] ?? null,
      ]
  );
  ```

## Provider-Specific Handling

### OpenAI
- **Structure**: `output[]` array with typed elements
- **Types**: `text`, `function_call`, `reasoning`, `web_search_call`
- **Tool Calls**: Separate elements with `function_call` type
- **Citations**: Web search results
- **Reasoning**: o1/o3 models

### Anthropic
- **Structure**: `content[]` array with blocks
- **Types**: `text`, `tool_use`, `thinking`
- **Tool Calls**: Within content blocks
- **Thinking**: Extended reasoning (if enabled)
- **Cache Metrics**: Creation + read tokens

## Notable Techniques

### 1. Finish Reason Mapping
- **Enum**: `FinishReason`: Stop | ToolCalls | Length
- **Mapped** from provider-specific values
- **OpenAI**: `stop`, `tool_calls`, `length`
- **Anthropic**: `end_turn`, `tool_use`, `max_tokens`

### 2. Additional Content Pattern
- **Flexible**: Array for provider-specific data
- **Examples**: Citations, thinking, reasoning, signatures
- **No schema**: Untyped but documented

### 3. Usage Object with Cache Metrics
- **Standard**: promptTokens, completionTokens
- **Extended**: cacheWriteInputTokens, cacheReadInputTokens
- **Provider support**: Anthropic has cache, OpenAI doesn't

### 4. Tool Call Routing
- **Pattern**: `match($finishReason)`
- **ToolCalls**: `handleToolCalls()` → execute → recurse
- **Stop/Length**: `handleStop()` → build response

## Architecture Insights

### Strengths
1. **Multi-step tracking**: Complete conversation history
2. **Usage aggregation**: Accurate total token counts
3. **Provider flexibility**: Additional content for extensions
4. **Type safety**: Readonly value objects

### Weaknesses
1. **Memory**: Stores all steps in memory
2. **Complexity**: Many classes for single response
3. **No streaming integration**: Separate path
