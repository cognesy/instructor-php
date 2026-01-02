# NeuronAI - Object Deserialization

## Core Files
- `/src/HandleStructured.php` - Main structured output orchestration (164 lines)
- `/src/Providers/OpenAI/HandleStructured.php` - OpenAI-specific schema passing (52 lines)
- `/src/Providers/Anthropic/HandleStructured.php` - Anthropic prompt-based approach (27 lines)
- `/src/StructuredOutput/JsonSchema.php` - JSON Schema generator from PHP class
- `/src/StructuredOutput/JsonExtractor.php` - Extracts JSON from LLM response text
- `/src/StructuredOutput/Deserializer/Deserializer.php` - JSON → PHP object hydration
- `/src/StructuredOutput/Validation/Validator.php` - Object validation

## Key Patterns

### Pattern 1: Retry Loop with Error Feedback
- **Location**: `HandleStructured.php:48-118`
- **Mechanism**: Do-while loop with retry counter + error correction messages
- **Code**:
  ```php
  public function structured(Message|array $messages, ?string $class = null, int $maxRetries = 1): mixed {
      $schema = JsonSchema::make()->generate($class);
      $error = '';

      do {
          try {
              // If previous attempt failed, add correction message
              if (trim($error) !== '') {
                  $correctionMessage = new UserMessage(
                      "There was a problem in your previous response that generated the following errors".
                      PHP_EOL.PHP_EOL.'- '.$error.PHP_EOL.PHP_EOL.
                      "Try to generate the correct JSON structure based on the provided schema."
                  );
                  $this->addToChatHistory($correctionMessage);
              }

              $response = $this->resolveProvider()
                  ->systemPrompt($this->resolveInstructions())
                  ->setTools($tools)
                  ->structured($messages, $class, $schema);

              $output = $this->processResponse($response, $schema, $class);
              return $output;
          } catch (Exception $ex) {
              $error = $ex->getMessage();
              $maxRetries--;
          }
      } while ($maxRetries >= 0);

      throw $exception;
  }
  ```
- **Self-correction**: LLM sees its own errors and retries

### Pattern 2: Provider-Specific Schema Application
- **OpenAI** (HandleStructured.php:17-37):
  ```php
  public function structured(array $messages, string $class, array $response_format): Message {
      $className = end(explode('\\', $class));

      $this->parameters = array_replace_recursive($this->parameters, [
          'response_format' => [
              'type' => 'json_schema',
              'json_schema' => [
                  'strict' => $this->strict_response,
                  "name" => $this->sanitizeClassName($className),
                  "schema" => $response_format,
              ],
          ]
      ]);

      return $this->chat($messages);  // Reuses chat flow
  }
  ```
  - **Native support**: Uses OpenAI's `response_format` parameter
  - **Strict mode**: Optional strict schema validation by provider
  - **Name sanitization**: Handles anonymous classes, special chars

- **Anthropic** (HandleStructured.php:15-26):
  ```php
  public function structured(array $messages, string $class, array $response_format): Message {
      $this->system .= PHP_EOL."# OUTPUT CONSTRAINTS".PHP_EOL.
          "Your response must be a JSON string following this schema: ".PHP_EOL.
          json_encode($response_format);

      return $this->chat($messages);  // Reuses chat flow
  }
  ```
  - **Prompt-based**: Appends schema to system prompt
  - **No native support**: Relies on LLM instruction-following
  - **JSON string**: Schema embedded as JSON in prompt

### Pattern 3: Three-Stage Processing
- **Location**: `processResponse()` method (lines 126-154)
- **Stages**:
  1. **Extract** JSON from text (JsonExtractor)
  2. **Deserialize** JSON to object (Deserializer)
  3. **Validate** object fields (Validator)

**Code**:
```php
protected function processResponse(Message $response, array $schema, string $class): object {
    // Stage 1: Extract
    $json = (new JsonExtractor())->getJson($response->getContent());
    if ($json === null || $json === '') {
        throw new AgentException("The response does not contains a valid JSON Object.");
    }

    // Stage 2: Deserialize
    $obj = Deserializer::make()->fromJson($json, $class);

    // Stage 3: Validate
    $violations = Validator::validate($obj);
    if (count($violations) > 0) {
        throw new AgentException(PHP_EOL.'- '.implode(PHP_EOL.'- ', $violations));
    }

    return $obj;
}
```

### Pattern 4: Observability Events
- **Events Fired**:
  - `schema-generation` / `schema-generated`
  - `structured-extracting` / `structured-extracted`
  - `structured-deserializing` / `structured-deserialized`
  - `structured-validating` / `structured-validated`
  - `error` (on failures)
- **Purpose**: Hook points for logging, monitoring, debugging
- **Location**: Throughout `HandleStructured.php` via `$this->notify()`

## Provider-Specific Handling

### OpenAI Structured Output
- **API Feature**: Native `response_format` with `json_schema` type
- **Schema Format**: Full JSON Schema passed to API
- **Strict Mode**: `strict: true|false` controls schema adherence
- **Class Name**: Used as schema identifier (sanitized)
- **Advantages**:
  - Provider-enforced validation
  - Higher reliability
  - Structured mode guarantees
- **Limitations**:
  - Requires OpenAI-compatible API
  - Schema must be valid JSON Schema
  - Not all models support strict mode

### Anthropic Structured Output
- **Approach**: Prompt engineering (system prompt injection)
- **Schema Format**: JSON-encoded schema in text
- **No Validation**: Provider doesn't enforce structure
- **Relies On**: LLM instruction-following capability
- **Advantages**:
  - Works with any model
  - No API restrictions
- **Limitations**:
  - Less reliable
  - More retries needed
  - LLM may ignore instructions

## Notable Techniques

### 1. Tool Call Recursion During Structured Output
- **Code** (lines 93-96):
  ```php
  if ($response instanceof ToolCallMessage) {
      $toolCallResult = $this->executeTools($response);
      return self::structured($toolCallResult, $class, $maxRetries);
  }
  ```
- **Why**: LLM may request tool use mid-structured-output
- **Behavior**: Executes tools, then retries structured call
- **Preserves**: Same class and retry count

### 2. Schema Generation from Class
- **Pattern**: `JsonSchema::make()->generate($class)`
- **Supports**: PHP classes with properties and types
- **Reflection-based**: Uses PHP reflection to introspect class
- **Returns**: Array (JSON Schema format)

### 3. JSON Extraction from Mixed Content
- **Component**: `JsonExtractor`
- **Handles**: Text with embedded JSON, markdown code blocks, extra text
- **Extracts**: First valid JSON object or array found
- **Fallback**: Returns null if no valid JSON

### 4. Validation via Attributes
- **Component**: `Validator`
- **Reads**: PHP validation attributes on class properties
- **Returns**: Array of violation messages
- **Throws**: AgentException with concatenated violations

### 5. Class Name Sanitization
- **Pattern**: `sanitizeClassName($name)`
- **Handles**:
  - Anonymous classes (`class@anonymous...` → `anonymous`)
  - Non-alphanumeric chars (replaced with `_`)
  - Leading non-letter (prefix with `class_`)
- **Why**: OpenAI schema names must be valid identifiers

### 6. Retry Count Decrement
- **Pattern**: `$maxRetries--` in catch block
- **Continues**: While `$maxRetries >= 0`
- **Allows**: `maxRetries=0` means 1 attempt (initial + 0 retries)

## Limitations/Edge Cases

### 1. No Partial Deserialization
- All-or-nothing: Either full object or exception
- No progressive hydration
- Cannot recover partial data from failed parse

### 2. Error Message Verbosity
- Entire error message sent back to LLM
- May include sensitive data or stack traces
- No filtering/sanitization of errors

### 3. Schema Generation Limitations
- Requires class with reflection metadata
- No support for dynamic schemas
- Cannot generate from examples or descriptions

### 4. Validation Timing
- Validation after deserialization
- Cannot validate JSON before parsing
- Wasted deserialization effort if validation fails

### 5. No Streaming Support
- Structured output always non-streamed
- Cannot progressively parse JSON from stream
- Wait for full response before processing

### 6. Tool Call Retry Logic
- Tool calls don't decrement retry counter
- Could loop indefinitely if tool always called
- No max tool invocations per structured call

### 7. Exception Swallowing
- `ToolMaxTriesException` re-thrown immediately
- Other exceptions caught and retried
- No distinction between retryable and fatal errors

### 8. Chat History Mutation
- Correction messages added to history
- Pollutes conversation with error feedback
- No cleanup of failed attempts

## Architecture Insights

### Strengths
1. **Self-correction**: LLM sees errors and retries
2. **Provider flexibility**: Native vs. prompt-based approaches
3. **Observability**: Rich event system for monitoring
4. **Type safety**: PHP classes as contracts
5. **Validation**: Attribute-based validation

### Weaknesses
1. **Tight coupling**: Deserializer and Validator not injected
2. **Error handling**: Catches broad `Exception` type
3. **No partial results**: All-or-nothing parsing
4. **History pollution**: Failed attempts in conversation
5. **No schema caching**: Re-generates schema every call

### Comparison to Typical Approaches
- **vs. Manual JSON parsing**: Type-safe objects vs. arrays
- **vs. DTO hydrators**: Reflection-based vs. manual mapping
- **vs. Serializer component**: Custom vs. Symfony Serializer
- **vs. Validation libraries**: Attributes vs. constraint classes

## Deserialization Flow
1. User calls `structured($messages, $class, $maxRetries)`
2. JSON schema generated from class via reflection
3. Provider-specific schema application (native or prompt)
4. LLM response received
5. Tool calls executed recursively if present
6. JSON extracted from response text
7. JSON deserialized to object instance
8. Object validated against constraints
9. Validation failures thrown as exception → retry
10. Success → object returned
