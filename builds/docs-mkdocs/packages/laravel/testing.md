# Testing

The package provides dedicated testing fakes for all four facades, allowing you to mock LLM responses and make assertions about how your code interacts with the services. No real API calls are made when a fake is active, which makes tests fast, deterministic, and free of external dependencies.

## StructuredOutput::fake()

The `StructuredOutputFake` intercepts all extraction calls and returns predefined responses. It records every call so you can assert against the response model class, messages, connection, and model that were used.

### Basic Usage

```php
use Cognesy\Instructor\Laravel\Facades\StructuredOutput;
use App\ResponseModels\PersonData;
use Tests\TestCase;

class PersonExtractionTest extends TestCase
{
    public function test_extracts_person_data(): void
    {
        // Arrange -- setup the fake with expected responses
        $fake = StructuredOutput::fake([
            PersonData::class => new PersonData(
                name: 'John Smith',
                age: 30,
                email: 'john@example.com',
            ),
        ]);

        // Act -- your code calls StructuredOutput
        $person = StructuredOutput::with(
            messages: 'John Smith is 30 years old',
            responseModel: PersonData::class,
        )->get();

        // Assert -- verify the result
        $this->assertEquals('John Smith', $person->name);
        $this->assertEquals(30, $person->age);

        // Assert that extraction was performed
        $fake->assertExtracted(PersonData::class);
    }
}
// @doctest id="22f0"
```

### Response Mapping

Map response model classes to their fake responses. Each class returns its corresponding value when extracted.

```php
$fake = StructuredOutput::fake([
    PersonData::class => new PersonData(name: 'John', age: 30),
    AddressData::class => new AddressData(city: 'New York'),
    OrderData::class => new OrderData(total: 99.99),
]);

// Each class returns its mapped response
$person = StructuredOutput::with(..., responseModel: PersonData::class)->get();
$address = StructuredOutput::with(..., responseModel: AddressData::class)->get();
// @doctest id="0f60"
```

If you request a response model that has no mapping, the fake throws a `RuntimeException` with a helpful message telling you which class needs a fake response.

### Response Sequences

Return different responses for sequential calls to the same response model class.

```php
$fake = StructuredOutput::fake();

$fake->respondWithSequence(PersonData::class, [
    new PersonData(name: 'First Person', age: 25),
    new PersonData(name: 'Second Person', age: 30),
    new PersonData(name: 'Third Person', age: 35),
]);

// First call
$first = StructuredOutput::with(...)->get();  // First Person

// Second call
$second = StructuredOutput::with(...)->get(); // Second Person

// Third call
$third = StructuredOutput::with(...)->get();  // Third Person
// @doctest id="e892"
```

### Available Assertions

```php
$fake = StructuredOutput::fake([...]);

// Run your code...

// Assert extraction was called for a class
$fake->assertExtracted(PersonData::class);

// Assert extraction count
$fake->assertExtractedTimes(PersonData::class, 1);
$fake->assertExtractedTimes(PersonData::class, 3);

// Assert no extractions were performed
$fake->assertNothingExtracted();

// Assert messages contained specific text
$fake->assertExtractedWith(PersonData::class, 'John Smith');

// Assert configured connection was used
$fake->assertUsedConnection('anthropic');

// Assert model was used
$fake->assertUsedModel('gpt-4o');
// @doctest id="e016"
```

### Accessing Recorded Calls

Inspect all recorded extraction calls for custom assertions.

```php
$fake = StructuredOutput::fake([...]);

// Run your code...

// Get all recorded extractions
$recorded = $fake->recorded();

foreach ($recorded as $extraction) {
    echo "Class: " . $extraction['class'];
    echo "Messages: " . json_encode($extraction['messages']);
    echo "Model: " . $extraction['model'];
    echo "Connection: " . $extraction['connection'];
}
// @doctest id="bf7e"
```

---

## Inference::fake()

The `InferenceFake` intercepts raw inference calls and returns responses based on pattern matching against the input messages.

### Basic Usage

```php
use Cognesy\Instructor\Laravel\Facades\Inference;
use Cognesy\Messages\Messages;

public function test_calls_inference(): void
{
    // Arrange
    $fake = Inference::fake([
        'What is 2+2?' => 'The answer is 4.',
        'default' => 'I don\'t know.',
    ]);

    // Act
    $response = Inference::with(
        messages: Messages::fromString('What is 2+2?'),
    )->get();

    // Assert
    $this->assertEquals('The answer is 4.', $response);
    $fake->assertCalled();
    $fake->assertCalledWith('What is 2+2?');
}
// @doctest id="055c"
```

### Pattern Matching

Responses are matched by checking whether the input message contains the pattern string. The first matching pattern wins. If no pattern matches, the `default` key is used as a fallback; if no `default` exists, an empty string is returned.

```php
$fake = Inference::fake([
    'capital' => 'Paris is the capital of France.',
    'weather' => 'The weather is sunny.',
    'default' => 'I don\'t understand.',
]);

// Matches 'capital' (input contains the word)
$response1 = Inference::with(messages: Messages::fromString('What is the capital of France?'))->get();

// Matches 'weather'
$response2 = Inference::with(messages: Messages::fromString('How is the weather today?'))->get();

// No match, uses 'default'
$response3 = Inference::with(messages: Messages::fromString('Random question'))->get();
// @doctest id="aa1a"
```

### Response Sequences

Queue ordered responses that are returned regardless of input content.

```php
$fake = Inference::fake();

$fake->respondWithSequence([
    'First response',
    'Second response',
    'Third response',
]);

// Returns responses in order
$first = Inference::with(...)->get();  // "First response"
$second = Inference::with(...)->get(); // "Second response"
// @doctest id="6208"
```

### Available Assertions

```php
$fake = Inference::fake([...]);

// Assert inference was called
$fake->assertCalled();

// Assert call count
$fake->assertCalledTimes(3);

// Assert never called
$fake->assertNotCalled();

// Assert called with specific message text
$fake->assertCalledWith('What is the capital');

// Assert configured connection was used
$fake->assertUsedConnection('groq');

// Assert model was used
$fake->assertUsedModel('llama-3.3-70b');

// Assert called with specific tools
$fake->assertCalledWithTools(['search', 'calculate']);
// @doctest id="c929"
```

---

## Embeddings::fake()

The `EmbeddingsFake` intercepts embedding requests and returns predefined or randomly generated vectors.

### Basic Usage

```php
use Cognesy\Instructor\Laravel\Facades\Embeddings;

public function test_generates_embeddings(): void
{
    // Arrange
    $fake = Embeddings::fake([
        'hello' => [0.1, 0.2, 0.3, 0.4, 0.5],
    ]);

    // Act
    $embedding = Embeddings::withInputs('hello world')->first();

    // Assert
    $this->assertIsArray($embedding);
    $fake->assertCalled();
    $fake->assertCalledWith('hello world');
}
// @doctest id="a3f1"
```

### Default Embeddings

If no pattern matches, a random normalized embedding vector is generated automatically. This is useful when you need an embedding but do not care about its exact values.

```php
$fake = Embeddings::fake();

// Returns random 1536-dimensional embedding (matching OpenAI's default dimensions)
$embedding = Embeddings::withInputs('anything')->first();

$this->assertCount(1536, $embedding);
// @doctest id="7c29"
```

### Custom Dimensions

Match the dimensionality of your production embedding model.

```php
$fake = Embeddings::fake()
    ->withDimensions(768); // Use 768 dimensions

$embedding = Embeddings::withInputs('test')->first();
$this->assertCount(768, $embedding);
// @doctest id="3d1f"
```

### Available Assertions

```php
$fake = Embeddings::fake([...]);

// Assert embeddings were called
$fake->assertCalled();

// Assert call count
$fake->assertCalledTimes(2);

// Assert never called
$fake->assertNotCalled();

// Assert called with specific input
$fake->assertCalledWith('hello world');

// Assert configured connection was used
$fake->assertUsedConnection('openai');

// Assert model was used
$fake->assertUsedModel('text-embedding-3-large');
// @doctest id="42f7"
```

---

## AgentCtrl::fake()

The `AgentCtrlFake` intercepts code agent executions and returns predefined responses without launching any CLI processes.

### Basic Usage

```php
use Cognesy\Instructor\Laravel\Facades\AgentCtrl;

public function test_generates_code(): void
{
    // Arrange -- setup fake with expected responses
    $fake = AgentCtrl::fake([
        'Generated migration file: 2024_01_01_create_users_table.php',
    ]);

    // Act -- your code calls AgentCtrl
    $result = AgentCtrl::claudeCode()
        ->execute('Generate a users table migration');

    // Assert
    $this->assertEquals(0, $result->exitCode);
    $this->assertStringContainsString('migration', $result->text());

    $fake->assertExecuted();
    $fake->assertExecutedWith('migration');
}
// @doctest id="ff1c"
```

### Response Sequences

Return different responses for sequential calls. If more calls are made than responses provided, the last response is repeated.

```php
$fake = AgentCtrl::fake([
    'First response',
    'Second response',
    'Third response',
]);

$first = AgentCtrl::claudeCode()->execute('First');   // "First response"
$second = AgentCtrl::claudeCode()->execute('Second'); // "Second response"
$third = AgentCtrl::claudeCode()->execute('Third');   // "Third response"

$fake->assertExecutedTimes(3);
// @doctest id="b8d2"
```

### Custom Responses

Create detailed fake responses with specific metadata using the `AgentCtrlFake::response()` factory method.

```php
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\Instructor\Laravel\Testing\AgentCtrlFake;

$customResponse = AgentCtrlFake::response(
    text: 'Generated code output',
    exitCode: 0,
    agentType: AgentType::ClaudeCode,
    cost: 0.05,
);

$fake = AgentCtrl::fake([$customResponse]);

$response = AgentCtrl::claudeCode()->execute('Test');

expect($response->cost)->toBe(0.05);
expect($response->agentType)->toBe(AgentType::ClaudeCode);
// @doctest id="498e"
```

### Fake Tool Calls

Simulate agent tool usage in your tests.

```php
use Cognesy\Instructor\Laravel\Testing\AgentCtrlFake;

$responseWithTools = AgentCtrlFake::response(
    text: 'Created file',
    toolCalls: [
        AgentCtrlFake::toolCall(
            tool: 'write_file',
            input: ['path' => 'app/Models/User.php'],
            output: 'File created successfully',
        ),
        AgentCtrlFake::toolCall(
            tool: 'run_tests',
            input: ['path' => 'tests/'],
            output: 'All tests passed',
        ),
    ],
);

$fake = AgentCtrl::fake([$responseWithTools]);

$response = AgentCtrl::claudeCode()->execute('...');

expect($response->toolCalls)->toHaveCount(2);
expect($response->toolCalls[0]->tool)->toBe('write_file');
// @doctest id="8db6"
```

### Available Assertions

```php
$fake = AgentCtrl::fake([...]);

// Run your code...

// Assert execution occurred
$fake->assertExecuted();
$fake->assertNotExecuted();
$fake->assertExecutedTimes(3);

// Assert prompt content
$fake->assertExecutedWith('Generate a migration');

// Assert agent type
$fake->assertAgentType(AgentType::ClaudeCode);
$fake->assertUsedClaudeCode();
$fake->assertUsedCodex();
$fake->assertUsedOpenCode();

// Assert streaming was used
$fake->assertStreaming();

// Access recorded executions for custom assertions
$executions = $fake->getExecutions();
foreach ($executions as $exec) {
    echo $exec['prompt'];
    echo $exec['agentType']->name;
    echo $exec['model'];
    echo $exec['timeout'];
    echo $exec['directory'];
    echo $exec['streaming'] ? 'yes' : 'no';
}

// Reset fake state between test scenarios
$fake->reset();
// @doctest id="9152"
```

### Testing Agent Services

```php
use Cognesy\Instructor\Laravel\Facades\AgentCtrl;

class CodeGeneratorService
{
    public function generateMigration(array $schema): string
    {
        $response = AgentCtrl::claudeCode()
            ->inDirectory(database_path('migrations'))
            ->execute("Generate migration for: " . json_encode($schema));

        if (!$response->isSuccess()) {
            throw new \RuntimeException('Code generation failed');
        }

        return $response->text();
    }
}

// Test
public function test_generates_migration(): void
{
    $fake = AgentCtrl::fake([
        'Migration created successfully',
    ]);

    $service = app(CodeGeneratorService::class);
    $result = $service->generateMigration(['table' => 'users']);

    $this->assertStringContainsString('Migration', $result);
    $fake->assertUsedClaudeCode();
    $fake->assertExecutedWith('users');
}
// @doctest id="99e5"
```

---

## HTTP Client Faking

Since the package routes all HTTP traffic through Laravel's HTTP client (`Illuminate\Http\Client\Factory`), you can also use `Http::fake()` to intercept requests at the HTTP transport level. This approach is lower-level than facade fakes and is useful when you need to test specific HTTP request/response shapes.

```php
use Illuminate\Support\Facades\Http;

public function test_with_http_fake(): void
{
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"name":"John","age":30}',
                    ],
                ],
            ],
        ]),
    ]);

    // Your StructuredOutput calls will use the fake HTTP response
    $person = StructuredOutput::with(...)->get();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/chat/completions';
    });
}
// @doctest id="6158"
```

This works because the `LaravelDriver` HTTP transport uses the same `Illuminate\Http\Client\Factory` instance that `Http::fake()` instruments. Make sure the `instructor.http.driver` config is set to `'laravel'` (the default).

---

## Testing Services

When testing services that use Instructor through dependency injection, the facade fake automatically replaces the container binding. The container will resolve the fake instance for both facade calls and injected dependencies.

```php
use Cognesy\Instructor\StructuredOutput;

class PersonExtractor
{
    public function __construct(
        private StructuredOutput $structuredOutput,
    ) {}

    public function extract(string $text): PersonData
    {
        return $this->structuredOutput
            ->with(messages: $text, responseModel: PersonData::class)
            ->get();
    }
}

// In your test
public function test_extracts_person(): void
{
    $fake = StructuredOutput::fake([
        PersonData::class => new PersonData(name: 'John', age: 30),
    ]);

    // The container will resolve the fake
    $extractor = app(PersonExtractor::class);
    $person = $extractor->extract('Some text');

    $this->assertEquals('John', $person->name);
}
// @doctest id="df29"
```

---

## Best Practices

### 1. Always Setup Fakes First

Call `fake()` before any code that might trigger an extraction. Setting up a fake after the fact has no effect on calls that already happened.

```php
public function test_example(): void
{
    // FIRST: Setup fake
    $fake = StructuredOutput::fake([...]);

    // THEN: Run your code
    $result = $this->service->process();

    // FINALLY: Assert
    $fake->assertExtracted(...);
}
// @doctest id="23e5"
```

### 2. Use Realistic Test Data

Realistic fake responses help catch bugs that only surface with production-like data, such as edge cases in string formatting or numeric precision.

```php
// Good -- realistic data
$fake = StructuredOutput::fake([
    InvoiceData::class => new InvoiceData(
        invoiceNumber: 'INV-2024-001',
        amount: 1234.56,
        dueDate: '2024-12-31',
    ),
]);

// Avoid -- placeholder data
$fake = StructuredOutput::fake([
    InvoiceData::class => new InvoiceData(
        invoiceNumber: 'test',
        amount: 0,
        dueDate: '',
    ),
]);
// @doctest id="bec3"
```

### 3. Test Edge Cases

Verify that your code handles empty collections, null optional fields, and other boundary conditions correctly.

```php
public function test_handles_empty_response(): void
{
    $fake = StructuredOutput::fake([
        ItemList::class => new ItemList(items: []),
    ]);

    $result = $this->service->getItems();

    $this->assertEmpty($result->items);
}

public function test_handles_null_optional_fields(): void
{
    $fake = StructuredOutput::fake([
        PersonData::class => new PersonData(
            name: 'John',
            age: 30,
            email: null, // Optional field
        ),
    ]);

    $person = $this->service->getPerson();

    $this->assertNull($person->email);
}
// @doctest id="f67c"
```

### 4. Verify Connection and Model Usage

Assert that your code routes requests to the correct provider and model, especially when different code paths use different connections.

```php
public function test_uses_correct_model(): void
{
    $fake = StructuredOutput::fake([...]);

    $this->service->processWithClaude();

    $fake->assertUsedConnection('anthropic');
    $fake->assertUsedModel('claude-3-5-sonnet-20241022');
}
// @doctest id="3ebb"
```
