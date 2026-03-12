# Facades

The package provides four Laravel facades that serve as the primary entry points for interacting with LLMs and code agents. Each facade resolves a fresh instance from the service container, so you can chain methods freely without worrying about shared state between calls.

## StructuredOutput

The primary facade for extracting structured data from unstructured text. Given a response model class (a plain PHP DTO with typed properties), the facade prompts the LLM, validates the response against the model's type constraints, and returns a fully typed object.

### Basic Usage

```php
use Cognesy\Instructor\Laravel\Facades\StructuredOutput;

$person = StructuredOutput::with(
    messages: 'John Smith is 30 years old',
    responseModel: PersonData::class,
)->get();
```

### With System Prompt

A system prompt steers the LLM's behavior for the extraction task. Use it to provide domain-specific instructions or constraints.

```php
$person = StructuredOutput::with(
    messages: 'Process this text: John, age 30',
    responseModel: PersonData::class,
    system: 'You are a data extraction assistant.',
)->get();
```

### With Examples (Few-Shot Learning)

Providing input/output examples helps the LLM understand the expected extraction pattern, especially for ambiguous or domain-specific data.

```php
$person = StructuredOutput::with(
    messages: 'Extract: Jane Doe, 25 years',
    responseModel: PersonData::class,
    examples: [
        ['input' => 'Bob is 40', 'output' => new PersonData(name: 'Bob', age: 40)],
    ],
)->get();
```

### Switching Connections

Each call can target a different LLM provider by specifying a connection name that matches an entry in your `config/instructor.php` connections array.

```php
$person = StructuredOutput::connection('anthropic')->with(
    messages: 'Extract person data...',
    responseModel: PersonData::class,
)->get();
```

### Fluent API

All configuration can also be set with individual fluent methods. This is useful when you build requests dynamically.

```php
use Cognesy\Instructor\StructuredOutputRuntime;

$person = StructuredOutput::withMessages('John is 30')
    ->withResponseModel(PersonData::class)
    ->withModel('gpt-4o')
    ->withRuntime(
        StructuredOutputRuntime::fromDefaults()->withMaxRetries(3)
    )
    ->get();
```

### Return Types

By default, `get()` returns the deserialized object matching your response model. For simpler extractions, convenience methods cast the result to scalar types.

```php
// Get as typed object (default)
$person = StructuredOutput::with(...)->get();

// Get as string
$name = StructuredOutput::with(...)->getString();

// Get as integer
$count = StructuredOutput::with(...)->getInt();

// Get as float
$price = StructuredOutput::with(...)->getFloat();

// Get as boolean
$valid = StructuredOutput::with(...)->getBoolean();

// Get as array
$items = StructuredOutput::with(...)->getArray();
```

### Available Methods

| Method | Description |
|--------|-------------|
| `connection(string $name)` | Switch to a different configured connection |
| `fromConfig(LLMConfig $config)` | Use an explicit typed LLM config object |
| `withRuntime(CanCreateStructuredOutput)` | Replace the runtime directly (advanced) |
| `with(...)` | Configure extraction with all parameters at once |
| `withMessages(...)` | Set the input messages |
| `withInput(string\|array\|object)` | Set arbitrary input data |
| `withResponseModel(string\|array\|object)` | Set the response model class, object, or array schema |
| `withResponseClass(string)` | Set the response model by class name |
| `withResponseObject(object)` | Set the response model by object instance |
| `withResponseJsonSchema(array\|CanProvideJsonSchema)` | Set the response model via JSON Schema |
| `withSystem(string)` | Set the system prompt |
| `withPrompt(string)` | Set the user prompt template |
| `withExamples(array)` | Set few-shot examples |
| `withModel(string)` | Override the model for this request |
| `withOptions(array)` | Set additional provider-specific options |
| `withOption(string, mixed)` | Set a single option key |
| `withStreaming(bool)` | Enable or disable streaming |
| `withCachedContext(...)` | Set a cached context for prompt caching |
| `intoArray()` | Deserialize the result as an array |
| `intoInstanceOf(string)` | Deserialize into the given class |
| `intoObject(CanDeserializeSelf)` | Deserialize using a self-deserializing object |
| `get()` | Execute extraction and return the result |
| `stream()` | Execute extraction and return a stream |
| `response()` | Execute and return the full response wrapper |
| `inferenceResponse()` | Execute and return the raw inference response |

Runtime policy such as retries, output mode, validators, transformers, deserializers, and extractors is configured on `StructuredOutputRuntime` and then passed via `withRuntime(...)`.

---

## Inference

For raw LLM inference without structured output extraction. Use this when you need free-form text generation, JSON responses, or tool-calling capabilities without the overhead of schema validation and deserialization.

### Basic Usage

```php
use Cognesy\Instructor\Laravel\Facades\Inference;
use Cognesy\Messages\Messages;

$response = Inference::with(
    messages: Messages::fromString('What is the capital of France?'),
)->get();

echo $response; // "The capital of France is Paris."
```

### With System Message

Pass a `Messages` object when you need fine-grained control over the conversation structure.

```php
use Cognesy\Messages\Messages;

$response = Inference::with(
    messages: Messages::fromArray([
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'Hello!'],
    ]),
)->get();
```

### JSON Response

Request a JSON-formatted response and parse it directly into a PHP array.

```php
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;

$data = Inference::with(
    messages: Messages::fromString('List 3 colors as JSON'),
    responseFormat: ResponseFormat::jsonObject(),
)->asJsonData();

// ['colors' => ['red', 'green', 'blue']]
```

### Switching Connections

```php
$response = Inference::connection('groq')->with(
    messages: Messages::fromString('Explain quantum computing'),
)->get();
```

### Available Methods

| Method | Description |
|--------|-------------|
| `connection(string $name)` | Switch to a different configured connection |
| `fromConfig(LLMConfig $config)` | Use an explicit typed LLM config object |
| `withRuntime(CanCreateInference)` | Replace the runtime directly (advanced) |
| `with(...)` | Configure with all parameters at once |
| `withMessages(Messages)` | Set the messages |
| `withModel(string)` | Override model |
| `withMaxTokens(int)` | Override max tokens |
| `withTools(ToolDefinitions)` | Add tool/function definitions |
| `withToolChoice(ToolChoice)` | Set tool choice strategy |
| `withResponseFormat(ResponseFormat)` | Set response format (e.g., JSON mode) |
| `withOptions(array)` | Set provider-specific options |
| `withStreaming(bool)` | Enable or disable streaming |
| `withCachedContext(...)` | Set a cached context for prompt caching |
| `withRetryPolicy(...)` | Set a custom retry policy |
| `withResponseCachePolicy(...)` | Set response cache behavior |
| `get()` | Execute and return text content |
| `asJson()` | Execute and return raw JSON string |
| `asJsonData()` | Execute and return parsed array |
| `response()` | Return the full response object |
| `stream()` | Return a stream iterator |

---

## Embeddings

For generating text embeddings (dense vector representations). Embeddings are useful for semantic search, clustering, classification, and similarity comparison.

### Basic Usage

```php
use Cognesy\Instructor\Laravel\Facades\Embeddings;

// Get single embedding
$embedding = Embeddings::withInputs('Hello world')->first();
// [0.123, -0.456, 0.789, ...]

// Get multiple embeddings
$embeddings = Embeddings::withInputs([
    'First text',
    'Second text',
])->vectors();
```

### Switching Connections

```php
$embedding = Embeddings::connection('ollama')
    ->withInputs('Local embedding test')
    ->first();
```

### With Custom Model

```php
$embedding = Embeddings::withInputs('Test')
    ->withModel('text-embedding-3-large')
    ->first();
```

### Full Response

The `get()` method returns the complete response object, which includes both the embedding vectors and usage statistics.

```php
$response = Embeddings::withInputs('Test')->get();

$vectors = $response->vectors();
$usage = $response->usage();
```

### Available Methods

| Method | Description |
|--------|-------------|
| `connection(string $name)` | Switch to a different configured embeddings connection |
| `fromConfig(EmbeddingsConfig $config)` | Use an explicit typed embeddings config object |
| `withRuntime(CanCreateEmbeddings)` | Replace the runtime directly (advanced) |
| `withInputs(string\|array)` | Set input text(s) to embed |
| `withModel(string)` | Override the embedding model |
| `withOptions(array)` | Set provider-specific options |
| `with(...)` | Configure with all parameters at once |
| `first()` | Get the first embedding vector |
| `vectors()` | Get all embedding vectors |
| `get()` | Get the full response object with vectors and usage |

---

## AgentCtrl

For invoking CLI-based code agents (Claude Code, Codex, OpenCode) that can execute code, modify files, and perform complex multi-step tasks. The facade provides a builder pattern for configuring agent execution and returns a structured `AgentResponse` with the generated output, tool calls, token usage, and cost.

### Basic Usage

```php
use Cognesy\Instructor\Laravel\Facades\AgentCtrl;

// Execute a task with Claude Code
$response = AgentCtrl::claudeCode()
    ->execute('Generate a Laravel migration for a users table');

if ($response->isSuccess()) {
    echo $response->text();
}
```

### Agent Selection

```php
// Claude Code (Anthropic)
$response = AgentCtrl::claudeCode()
    ->withModel('claude-opus-4-5')
    ->execute('Refactor the User model');

// Codex (OpenAI)
$response = AgentCtrl::codex()
    ->execute('Write unit tests for UserService');

// OpenCode (Multi-model)
$response = AgentCtrl::openCode()
    ->withModel('anthropic/claude-sonnet-4-5')
    ->execute('Analyze codebase architecture');

// Dynamic selection
use Cognesy\AgentCtrl\Enum\AgentType;

$response = AgentCtrl::make(AgentType::ClaudeCode)
    ->execute('Generate API documentation');
```

### Configuration

The facade automatically applies Laravel configuration defaults from `config/instructor.php` for each agent type. Builder methods override those defaults for a single call.

```php
use Cognesy\AgentCtrl\Config\AgentConfig;
use Cognesy\Sandbox\Enums\SandboxDriver;

$response = AgentCtrl::claudeCode()
    ->withConfig(new AgentConfig(
        model: 'claude-opus-4-5',
        timeout: 300,
        workingDirectory: base_path(),
        sandboxDriver: SandboxDriver::Host,
    ))
    ->execute('Your prompt');
```

### Streaming

Process output in real-time with streaming callbacks. The `onText`, `onToolUse`, and `onComplete` callbacks fire as the agent generates output.

```php
$response = AgentCtrl::claudeCode()
    ->onText(function (string $text) {
        echo $text;
    })
    ->onToolUse(function (string $tool, array $input, ?string $output) {
        echo "Tool: $tool\n";
    })
    ->onComplete(function (AgentResponse $response) {
        echo "Done! Exit code: " . $response->exitCode;
    })
    ->executeStreaming('Generate a REST API');
```

### Response Object

```php
$response = AgentCtrl::claudeCode()->execute('...');

// Main content
$response->text();           // Generated text output
$response->isSuccess();      // True if exitCode is 0

// Metadata
$response->exitCode;         // Process exit code
$response->sessionId();      // Session ID for resuming (AgentSessionId|null)
$response->agentType;        // Which agent was used

// Usage & cost
$response->usage->input;     // Input tokens
$response->usage->output;    // Output tokens
$response->cost;             // Cost in USD

// Tool calls
foreach ($response->toolCalls as $call) {
    $call->tool;             // Tool name
    $call->input;            // Tool input
    $call->output;           // Tool output
    $call->isError;          // If tool failed
}
```

### Session Management

Resume previous sessions for multi-turn agent interactions. The session ID from a previous response lets you continue where you left off.

```php
// First execution
$response = AgentCtrl::claudeCode()
    ->execute('Start refactoring the User model');

$sessionId = $response->sessionId;

// Resume later
$response = AgentCtrl::claudeCode()
    ->resumeSession($sessionId)
    ->execute('Continue with the Address model');
```

### Available Methods

| Method | Description |
|--------|-------------|
| `claudeCode()` | Get Claude Code agent builder |
| `codex()` | Get Codex agent builder |
| `openCode()` | Get OpenCode agent builder |
| `make(AgentType)` | Get agent builder by type |
| `fake(array $responses)` | Create a testing fake |
| `withConfig(AgentConfig)` | Apply shared typed config |
| `withModel(string)` | Set AI model |
| `withTimeout(int)` | Set execution timeout in seconds |
| `inDirectory(string)` | Set working directory |
| `withSandboxDriver(SandboxDriver)` | Set sandbox isolation driver |
| `onText(callable)` | Register streaming text callback |
| `onToolUse(callable)` | Register tool use callback |
| `onComplete(callable)` | Register completion callback |
| `resumeSession(string)` | Resume a previous session |
| `execute(string)` | Execute and return response |
| `executeStreaming(string)` | Execute with streaming callbacks |

---

## Dependency Injection

Instead of facades, you can inject the underlying service classes directly into your constructors or method signatures. Laravel's service container resolves them with the same configuration and HTTP client bindings that the facades use.

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Embeddings\Embeddings;

class MyService
{
    public function __construct(
        private StructuredOutput $structuredOutput,
        private Inference $inference,
        private Embeddings $embeddings,
    ) {}

    public function process(string $text): PersonData
    {
        return $this->structuredOutput
            ->with(messages: $text, responseModel: PersonData::class)
            ->get();
    }
}
```

Dependency injection is particularly useful for:
- **Better testability** -- you can mock the injected service or use constructor injection with a fake
- **Explicit dependencies** -- the class signature documents exactly which services it needs
- **IDE autocompletion** -- your editor can provide method suggestions on the typed property

---

## Facade Behavior

All facades proxy to the underlying service classes registered in the container. The `StructuredOutput` facade is registered as a non-singleton (`bind`), so each resolution returns a fresh instance. `Inference` and `Embeddings` are registered as singletons. This means you can chain methods on any facade call without side effects:

```php
// Each call gets a fresh StructuredOutput instance
StructuredOutput::connection('openai')->with(...)->get();
StructuredOutput::connection('anthropic')->with(...)->get();
```
