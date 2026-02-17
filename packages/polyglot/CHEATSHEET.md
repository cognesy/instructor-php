# Polyglot API Overview (Enhanced)

## Main Entry Point Classes

### Inference
**`Inference`** - Main facade for LLM inference operations with trait-based API
- `new($events, $configProvider)` - Constructor with optional event handler and config provider
- `registerDriver($name, $driver)` - Register custom inference driver

**Provider Management (via HandlesLLMProvider trait):**
- `withLLMProvider($provider)` - Set custom LLM provider
- `withLLMConfig($config)` - Set explicit LLM configuration
- `withConfigProvider($provider)` - Set configuration provider
- `withDsn($dsn)` - Configure via DSN string
- `using($preset)` - Use predefined configuration preset
- `withHttpClient($client)` - Set custom HTTP client
- `withHttpClientPreset($preset)` - Set HTTP client preset
- `withLLMConfigOverrides($overrides)` - Override specific config values
- `withDriver($driver)` - Set explicit inference driver
- `withHttpDebugPreset($preset)` - Set debug configuration

**Request Building (via HandlesRequestBuilder trait):**
- `withMessages($messages)` - Set conversation messages (string or array)
- `withModel($model)` - Set model name
- `withMaxTokens($maxTokens)` - Set maximum tokens
- `withTools($tools)` - Set available tools
- `withToolChoice($toolChoice)` - Set tool selection preference
- `withResponseFormat($format)` - Set response format constraints
- `withOptions($options)` - Set inference options (temperature, max tokens, etc.)
- `withStreaming($stream)` - Enable/disable streaming
- `withOutputMode($mode)` - Set output mode
- `withCachedContext($messages, $tools, $toolChoice, $responseFormat)` - Set cached context

**Invocation (via HandlesInvocation trait):**
- `withRequest($request)` - Set explicit inference request
- `with($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode)` - Set all parameters at once
- `create()` - Create PendingInference instance

**Shortcuts (via HandlesShortcuts trait):**
- `stream()` - Get streaming response
- `response()` - Get full response object
- `get()` - Get response content as string
- `asJson()` - Get response as JSON string
- `asJsonData()` - Get response as array

### Embeddings
**`Embeddings`** - Main facade for text embedding operations with trait-based API
- `new($events, $configProvider)` - Constructor with optional event handler and config provider
- `registerDriver($name, $driver)` - Register custom embedding driver

**Initialization (via HandlesInitMethods trait):**
- `using($preset)` - Use predefined configuration preset
- `withPreset($preset)` - Set configuration preset
- `withDsn($dsn)` - Configure via DSN string
- `withConfig($config)` - Set explicit embeddings configuration
- `withConfigProvider($provider)` - Set configuration provider
- `withDriver($driver)` - Set explicit vectorization driver
- `withProvider($provider)` - Set embeddings provider
- `withHttpClient($client)` - Set custom HTTP client
- `withHttpDebugPreset($preset)` - Set debug configuration

**Fluent Methods (via HandlesFluentMethods trait):**
- `withInputs($input)` - Set input text/array for embedding
- `withModel($model)` - Set model name
- `withOptions($options)` - Set embedding options

**Invocation (via HandlesInvocation trait):**
- `withRequest($request)` - Set explicit embeddings request
- `with($input, $options, $model)` - Set all parameters at once
- `create()` - Create PendingEmbeddings instance

**Shortcuts (via HandlesShortcuts trait):**
- `get()` - Get full embeddings response
- `vectors()` - Get all embedding vectors
- `first()` - Get first embedding vector

## Response Processing Classes

### PendingInference
**`PendingInference`** - Handles inference responses and format conversion
- `new($request, $driver, $eventDispatcher)` - Constructor
- `isStreamed()` - Check if response is streamed
- `get()` - Get response content as string
- `stream()` - Get streaming response (throws if not streamed)
- `asJson()` - Get response as JSON string
- `asJsonData()` - Get response as array
- `response()` - Get full InferenceResponse object

### InferenceStream
**`InferenceStream`** - Handles streaming responses from language models
- `new($request, $driver, $eventDispatcher)` - Constructor
- `responses()` - Generator yielding partial responses
- `all()` - Get all partial responses as array
- `final()` - Get final accumulated response
- `onPartialResponse($callback)` - Set callback for partial responses

### PendingEmbeddings
**`PendingEmbeddings`** - Handles embeddings responses
- `new($request, $driver, $events)` - Constructor
- `request()` - Get original request
- `get()` - Get embeddings response
- `makeResponse()` - Create response from driver

## Provider Classes

### LLMProvider
**`LLMProvider`** - Builder for configuring LLM inference drivers
- `new($events, $configProvider)` - Create new provider instance
- `using($preset)` - Use predefined configuration preset
- `dsn($dsn)` - Configure via DSN string
- `withLLMPreset($preset)` - Set LLM preset
- `withLLMConfig($config)` - Set explicit LLM configuration
- `withConfigOverrides($overrides)` - Override config values
- `withConfigProvider($provider)` - Set config provider
- `withDsn($dsn)` - Set DSN string
- `withDriver($driver)` - Set explicit inference driver
- Use `resolveConfig()` + `InferenceDriverFactory::makeDriver()` to create drivers

### EmbeddingsProvider
**`EmbeddingsProvider`** - Builder for configuring embeddings drivers
- `new($events, $configProvider)` - Create new provider instance
- `using($preset)` - Use predefined configuration preset
- `dsn($dsn)` - Configure via DSN string
- `withPreset($preset)` - Set configuration preset
- `withDsn($dsn)` - Set DSN string
- `withConfig($config)` - Set explicit embeddings configuration
- `withConfigProvider($provider)` - Set config provider
- `withDriver($driver)` - Set explicit vectorization driver
- Use `resolveConfig()` + `EmbeddingsDriverFactory::makeDriver()` to create drivers

## Request/Response Classes

### InferenceRequest
**`InferenceRequest`** - Configuration for inference operations
- `new($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode, $cachedContext)` - Constructor
- `withMessages($messages)` - Set conversation messages
- `withModel($model)` - Set model name
- `withTools($tools)` - Set available tools
- `withToolChoice($toolChoice)` - Set tool selection preference
- `withResponseFormat($format)` - Set response format constraints
- `withOptions($options)` - Set inference options
- `withStreaming($streaming)` - Enable/disable streaming
- `withOutputMode($mode)` - Set output mode
- `withCachedContext($context)` - Set cached context
- `messages()`, `model()`, `tools()`, `options()`, `outputMode()`, `cachedContext()` - Getters
- `isStreamed()` - Check if streaming enabled
- `hasTools()`, `hasMessages()`, `hasModel()`, `hasOptions()` - Validation helpers
- `withCacheApplied()` - Apply cached context to request
- `toArray()` - Convert to array representation

### InferenceResponse
**`InferenceResponse`** - Response from inference operations
- `new($content, $finishReason, $toolCalls, $reasoningContent, $usage, $responseData, $isPartial, $partialResponses)` - Constructor
- `fromPartialResponses($partialResponses)` - Create from partial responses
- `content()` - Get response text content
- `withContent($content)` - Set content
- `reasoningContent()` - Get reasoning content
- `withReasoningContent($content)` - Set reasoning content
- `toolCalls()` - Get tool calls made by model
- `usage()` - Get token usage statistics
- `finishReason()` - Get completion reason
- `value()` - Get processed/transformed value
- `withValue($value)` - Set processed value
- `hasContent()`, `hasToolCalls()`, `hasValue()`, `hasReasoningContent()` - Validation helpers
- `findJsonData($mode)` - Extract JSON from response
- `isPartial()` - Check if response is partial
- `partialResponses()` - Get partial responses
- `lastPartialResponse()` - Get last partial response
- `toArray()` - Convert to array representation

### EmbeddingsRequest
**`EmbeddingsRequest`** - Configuration for embedding operations
- `new($input, $options, $model)` - Constructor with input text/array
- `inputs()` - Get input data array
- `options()` - Get embedding options
- `model()` - Get model name
- `toArray()` - Convert to array representation

### EmbeddingsResponse
**`EmbeddingsResponse`** - Response from embedding operations
- `new($vectors, $usage)` - Constructor
- `vectors()` - Get all embedding vectors
- `all()` - Get all vectors (alias for vectors())
- `first()` - Get first vector
- `last()` - Get last vector
- `split($index)` - Split vectors at index
- `usage()` - Get token usage statistics
- `toValuesArray()` - Get raw vector values as arrays
- `toArray()` - Convert to array representation

## Data Classes

### Vector
**`Vector`** - Represents an embedding vector
- `values()` - Get vector values array
- `toArray()` - Convert to array representation

### Usage
**`Usage`** - Token usage statistics
- `toArray()` - Get usage data as array
- `accumulate($usage)` - Accumulate usage from another instance
- `clone()` - Create a copy

### ToolCalls
**`ToolCalls`** - Collection of tool calls
- `hasAny()` - Check if any tool calls exist
- `hasSingle()` - Check if exactly one tool call
- `first()` - Get first tool call
- `toArray()` - Convert to array
- `clone()` - Create a copy

### ToolCall
**`ToolCall`** - Single tool call
- `args()` - Get tool call arguments

### PartialInferenceResponse
**`PartialInferenceResponse`** - Partial response from streaming
- `withContent($content)` - Set accumulated content
- `withReasoningContent($content)` - Set accumulated reasoning content
- `withFinishReason($reason)` - Set finish reason
- `hasToolName()`, `hasToolArgs()` - Tool validation helpers
- `toolName()`, `toolArgs()` - Get tool information

## Enums

### OutputMode
Defines inference output modes: `Unrestricted`, `Tools`, `Json`, `JsonSchema`, `MdJson`, `Text`

### InferenceFinishReason
Completion reasons: `Stop`, `Length`, `ToolCalls`, `ContentFilter`, `Error`

### InferenceContentType
Content types for inference

## Typical Usage Patterns

### Basic Inference
```php
$response = (new Inference())
    ->using('openai/gpt-4')
    ->withMessages('Hello, world!')
    ->get();
```

### Streaming Inference
```php
$stream = (new Inference())
    ->using('openai/gpt-4')
    ->withMessages('Tell me a story')
    ->withStreaming(true)
    ->stream();

foreach ($stream->responses() as $partial) {
    echo $partial->content();
}
```

### Tool Usage
```php
$response = (new Inference())
    ->using('openai/gpt-4')
    ->withMessages('What is the weather like?')
    ->withTools($tools)
    ->withToolChoice('auto')
    ->response();
```

### Basic Embeddings
```php
$embeddings = (new Embeddings())
    ->using('openai/text-embedding-3-small')
    ->withInputs(['Hello', 'World'])
    ->get();
```
