# NeuronAI - Request Normalization

## Core Files
- `/src/Providers/MessageMapperInterface.php` - Contract for message normalization
- `/src/Providers/OpenAI/MessageMapper.php` - OpenAI-specific mapper (173 lines)
- `/src/Providers/Anthropic/MessageMapper.php` - Anthropic-specific mapper (142 lines)
- `/src/Chat/Messages/Message.php` - Base unified message class
- `/src/Chat/Messages/ToolCallMessage.php` - Tool invocation message
- `/src/Chat/Messages/ToolCallResultMessage.php` - Tool execution result
- `/src/Chat/Attachments/Attachment.php` - Attachment value object

## Key Patterns

### Pattern 1: Unified Message Hierarchy
- **Location**: `/src/Chat/Messages/`
- **Mechanism**: Single base `Message` class with specialized subclasses
- **Structure**:
  ```php
  Message                        // Base class: role + content + attachments + usage + metadata
  ├── UserMessage               // Inherits all Message fields
  ├── AssistantMessage          // Inherits all Message fields
  ├── ToolCallMessage           // Extends AssistantMessage + tools[]
  └── ToolCallResultMessage     // Contains tools[] with results
  ```
- **Fields**:
  - `MessageRole $role` - Enum: USER, ASSISTANT, SYSTEM, TOOL
  - `mixed $content` - String or array
  - `Attachment[] $attachments` - Images/documents
  - `Usage $usage` - Token counts
  - `array $meta` - Provider-specific metadata

### Pattern 2: Provider-Specific MessageMapper
- **Location**: Each provider has `MessageMapper implements MessageMapperInterface`
- **Mechanism**:
  - Takes unified `Message[]` array
  - Returns provider-specific array format
  - Uses `match()` on message class type
  - Strips usage metadata before sending
- **Code**:
  ```php
  public function map(array $messages): array {
      foreach ($messages as $message) {
          $item = match ($message::class) {
              Message::class,
              UserMessage::class,
              AssistantMessage::class => $this->mapMessage($message),
              ToolCallMessage::class => $this->mapToolCall($message),
              ToolCallResultMessage::class => $this->mapToolsResult($message),
          };
      }
  }
  ```

### Pattern 3: Multi-Modal Content Normalization
- **Location**: `mapMessage()` methods in each mapper
- **Mechanism**:
  - String content → `[{type: 'text', text: '...'}]` array
  - Attachments appended to content array
  - Each provider handles attachment format differently

## Provider-Specific Handling

### OpenAI
- **Content Structure**: `content: [{type, text|image_url|file}, ...]`
- **Images**:
  ```php
  ['type' => 'image_url', 'image_url' => ['url' => $url]]
  ```
  - Supports URL, base64 (as data URI), or file ID
- **Documents**:
  ```php
  ['type' => 'file', 'file' => ['filename' => ..., 'file_data' => 'data:mime;base64,...']]
  ['type' => 'file', 'file' => ['file_id' => $id]]
  ```
- **Tool Results**: Returns array of separate tool messages
  ```php
  [
      ['role' => 'tool', 'tool_call_id' => $id, 'content' => $result],
      ['role' => 'tool', 'tool_call_id' => $id2, 'content' => $result2],
  ]
  ```

### Anthropic
- **Content Structure**: `content: [{type, text|source}, ...]`
- **System Prompt**: Separate `system` parameter (not part of messages array)
- **Images**:
  ```php
  [
      'type' => 'image',
      'source' => [
          'type' => 'base64',
          'media_type' => 'image/png',
          'data' => $base64_content
      ]
  ]
  ```
- **Documents**:
  ```php
  [
      'type' => 'document',
      'source' => ['type' => 'file', 'file_id' => $id]
  ]
  ```
- **Tool Calls**: Single message with multiple tool_use blocks
  ```php
  [
      'role' => 'assistant',
      'content' => [
          ['type' => 'text', 'text' => '...'],  // Optional
          ['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => {...}],
      ]
  ]
  ```
- **Tool Results**: Single user message with tool_result blocks
  ```php
  [
      'role' => 'user',
      'content' => [
          ['type' => 'tool_result', 'tool_use_id' => $id, 'content' => $result],
      ]
  ]
  ```

## Notable Techniques

### 1. Usage Metadata Stripping
- **Why**: Usage info is response metadata, not request data
- **Implementation**: `unset($payload['usage'])` before sending
- **Location**: Lines 64-66 in OpenAI mapper, 49-51 in Anthropic mapper

### 2. List vs. Single Item Return
- **OpenAI**: Tool results return list (multiple tool messages)
- **Check**: `if (array_is_list($item)) { merge } else { append }`
- **Location**: Lines 47-51 in OpenAI mapper

### 3. Attachment Content Type Dispatch
- **Enum**: `AttachmentContentType`: URL | BASE64 | ID
- **Pattern**: `match($attachment->contentType)` to route to correct format
- **Supports**: Images (PNG, JPG, GIF, WebP), Documents (PDF)

### 4. Empty Tool Inputs Handling
- **Anthropic**: Empty inputs → `new stdClass()` (required by API)
- **Location**: Line 120 in Anthropic mapper
- **Reason**: Anthropic requires `input` field even if empty

## Limitations/Edge Cases

### 1. No Message Merging
- Sequential same-role messages sent as-is
- Some providers (Gemini) require role alternation
- No automatic merging/grouping logic

### 2. Attachment Type Detection
- Limited to `AttachmentType::DOCUMENT` vs. default (image)
- No explicit AUDIO, VIDEO types (would need extension)

### 3. System Prompt Handling
- OpenAI: System prompt prepended as message in array
- Anthropic: System prompt in separate parameter (handled at request level, not mapper)
- Inconsistent abstraction between providers

### 4. Tool Call ID Generation
- Each provider uses different ID formats
- OpenAI generates IDs in response
- Anthropic generates IDs in request
- No ID validation/format checking

### 5. Content Array Conversion
- Always converts string → array format
- Even if provider supports string (inefficient for simple cases)
- No conditional logic based on attachment presence

## Architecture Insights

### Strengths
1. **Clean separation**: Mapper per provider, unified message types
2. **Immutable messages**: Messages are value objects
3. **Type safety**: Strong typing with enums and interfaces
4. **Extensibility**: Easy to add new message types or providers

### Weaknesses
1. **No validation**: Mappers assume well-formed input
2. **Tight coupling**: Message classes know about JSON serialization
3. **No caching**: Repeated mapping of same messages
4. **Limited composition**: No message builder pattern

### Comparison to Typical Approaches
- **vs. Array-only**: Type safety + IDE support
- **vs. Builder pattern**: Less verbose but less flexible
- **vs. DTO**: Messages are both domain objects and DTOs (mixed responsibility)
