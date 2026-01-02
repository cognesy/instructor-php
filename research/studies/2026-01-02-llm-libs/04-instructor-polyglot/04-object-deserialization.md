# InstructorPHP Polyglot - Object Deserialization

## Core Architecture

### Architectural Boundary
- **Polyglot**: LLM API abstraction (HTTP communication only)
- **Instructor**: Structured output deserialization (separate package)
- **Separation**: Polyglot returns raw JSON strings, Instructor handles deserialization

## Polyglot's Role

### Pattern: No Deserialization in Polyglot
- **Responsibility**: Returns `InferenceResponse` with raw content string
- **Code**:
  ```php
  class InferenceResponse {
      public string $content;          // Raw JSON string
      public string $finishReason;
      public ToolCalls $toolCalls;
      public Usage $usage;
      public HttpResponse $responseData;
  }
  ```
- **No validation**: Polyglot doesn't validate JSON structure
- **No typing**: Content is untyped string

### Pattern: ResponseFormat Data Model
- **File**: `/src/Inference/Data/ResponseFormat.php`
- **Structure**:
  ```php
  class ResponseFormat {
      private ?string $name;
      private ?array $schema;        // JSON Schema array
      private ?string $type;
      private ?bool $strict;

      public function as(OutputMode $mode): array {
          return match ($mode) {
              OutputMode::Json => $this->asJsonObject(),
              OutputMode::JsonSchema => $this->asJsonSchema(),
              OutputMode::Text => $this->asText(),
          };
      }
  }
  ```
- **Handlers**: Provider-specific conversion via closures
- **Usage**: Passed to LLM API, but Polyglot doesn't process response

## Instructor Package (Out of Scope)

### Deserialization Location
- **Package**: `packages/instructor/src/Deserialization/`
- **Files**:
  - `ResponseDeserializer.php` - Main deserializer
  - `Deserializers/SymfonyDeserializer.php` - Symfony Serializer integration
  - `Deserializers/CustomObjectNormalizer.php` - Custom denormalizer
  - `Deserializers/BackedEnumNormalizer.php` - Enum support
  - `Deserializers/FlexibleDateDenormalizer.php` - Date handling

### Pattern: Separate Package
- **Polyglot**: Low-level LLM API client
- **Instructor**: High-level structured output wrapper
- **Integration**: Instructor uses Polyglot for HTTP, adds deserialization layer

## Notable Techniques

### 1. Clean Separation
- **Polyglot**: No object hydration logic
- **Single responsibility**: Only HTTP + response parsing
- **Extensible**: Any deserialization layer can be added on top

### 2. Schema Transport
- **ResponseFormat**: Carries JSON schema
- **Passed to LLM**: Via request body
- **Not used for validation**: Polyglot doesn't validate against schema

### 3. Content as String
- **No typing**: `$response->content` is always string
- **No parsing**: Client must parse JSON
- **No validation**: No schema enforcement

## Architecture Insights

### Strengths
1. **Clean separation**: API communication vs. deserialization
2. **Reusable**: Polyglot can be used standalone
3. **Flexible**: Any deserialization strategy can be layered on top

### Weaknesses
1. **No validation**: Invalid JSON passes through
2. **No type safety**: Content is untyped
3. **Manual parsing**: Client must decode JSON

## Comparison to Other Libraries

### Different Approach
- **NeuronAI**: Includes deserialization in core
- **Prism**: Includes structured output in core
- **Symfony AI**: Minimal - returns arrays
- **InstructorPHP**: Separated into 2 packages (polyglot + instructor)

### Trade-offs
- **Pro**: Polyglot is simpler, more reusable
- **Con**: Requires additional package for structured output
- **Pro**: Clear architectural boundary
- **Con**: More packages to manage
