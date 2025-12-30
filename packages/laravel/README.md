# Instructor for Laravel

Laravel integration for [Instructor PHP](https://github.com/cognesy/instructor-php) - the structured output library for LLMs.

## Installation

```bash
composer require cognesy/instructor-laravel
```

The package uses Laravel auto-discovery, so the service provider registers automatically.

### Publish Configuration

```bash
php artisan vendor:publish --tag=instructor-config
```

This publishes `config/instructor.php` where you can configure connections, logging, and more.

## Quick Start

### 1. Configure API Keys

Add your API key to `.env`:

```env
OPENAI_API_KEY=your-openai-api-key
# or
ANTHROPIC_API_KEY=your-anthropic-api-key
```

### 2. Create a Response Model

```bash
php artisan make:response-model PersonData
```

Edit the generated class:

```php
// app/ResponseModels/PersonData.php
final class PersonData
{
    public function __construct(
        /** The person's full name */
        public readonly string $name,

        /** The person's age in years */
        public readonly int $age,

        /** The person's email address */
        public readonly ?string $email = null,
    ) {}
}
```

### 3. Extract Structured Data

```php
use Cognesy\Instructor\Laravel\Facades\StructuredOutput;
use App\ResponseModels\PersonData;

$person = StructuredOutput::with(
    messages: 'John Smith is 30 years old and works at john@example.com',
    responseModel: PersonData::class,
)->get();

// $person->name === 'John Smith'
// $person->age === 30
// $person->email === 'john@example.com'
```

## Facades

The package provides three main facades:

### StructuredOutput

For extracting structured data from text:

```php
use Cognesy\Instructor\Laravel\Facades\StructuredOutput;

$data = StructuredOutput::with(
    messages: 'Your input text here',
    responseModel: YourModel::class,
)->get();
```

### Inference

For raw LLM inference:

```php
use Cognesy\Instructor\Laravel\Facades\Inference;

$response = Inference::with(
    messages: 'What is the capital of France?',
)->get();
```

### Embeddings

For generating text embeddings:

```php
use Cognesy\Instructor\Laravel\Facades\Embeddings;

$embedding = Embeddings::withInputs('Your text here')->first();
```

## Configuration

### Multiple Connections

Configure multiple LLM providers in `config/instructor.php`:

```php
'connections' => [
    'openai' => [
        'driver' => 'openai',
        'api_key' => env('OPENAI_API_KEY'),
        'model' => 'gpt-4o',
    ],

    'anthropic' => [
        'driver' => 'anthropic',
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => 'claude-3-5-sonnet-20241022',
    ],

    'groq' => [
        'driver' => 'groq',
        'api_key' => env('GROQ_API_KEY'),
        'model' => 'llama-3.3-70b-versatile',
    ],
],
```

Switch between connections:

```php
$data = StructuredOutput::using('anthropic')->with(
    messages: 'Extract data...',
    responseModel: YourModel::class,
)->get();
```

### Supported Drivers

- `openai` - OpenAI (GPT-4, GPT-4o, etc.)
- `anthropic` - Anthropic (Claude 3, 3.5)
- `azure` - Azure OpenAI
- `gemini` - Google Gemini
- `mistral` - Mistral AI
- `groq` - Groq
- `cohere` - Cohere
- `deepseek` - DeepSeek
- `ollama` - Ollama (local)
- `perplexity` - Perplexity

## Testing

The package provides testing fakes that make it easy to mock LLM responses:

### StructuredOutput::fake()

```php
use Cognesy\Instructor\Laravel\Facades\StructuredOutput;
use App\ResponseModels\PersonData;

public function test_extracts_person_data(): void
{
    // Arrange - Setup fake
    $fake = StructuredOutput::fake([
        PersonData::class => new PersonData(name: 'John', age: 30),
    ]);

    // Act - Your code calls StructuredOutput
    $service = new MyService();
    $person = $service->extractPerson('John is 30 years old');

    // Assert
    $this->assertEquals('John', $person->name);
    $this->assertEquals(30, $person->age);

    // Assert StructuredOutput was called
    $fake->assertExtracted(PersonData::class);
    $fake->assertExtractedTimes(PersonData::class, 1);
}
```

### Available Assertions

```php
// Assert extraction was called
$fake->assertExtracted(PersonData::class);
$fake->assertExtractedTimes(PersonData::class, 2);
$fake->assertNothingExtracted();

// Assert messages contained text
$fake->assertExtractedWith(PersonData::class, 'John is 30');

// Assert preset/model used
$fake->assertUsedPreset('openai');
$fake->assertUsedModel('gpt-4o');
```

### Response Sequences

```php
$fake = StructuredOutput::fake();
$fake->respondWithSequence(PersonData::class, [
    new PersonData(name: 'First', age: 25),
    new PersonData(name: 'Second', age: 30),
    new PersonData(name: 'Third', age: 35),
]);

// First call returns 'First', second returns 'Second', etc.
```

### Inference::fake()

```php
use Cognesy\Instructor\Laravel\Facades\Inference;

$fake = Inference::fake([
    'What is 2+2?' => 'The answer is 4.',
    'default' => 'I don\'t know.',
]);

// Your code calls Inference
$response = Inference::with(messages: 'What is 2+2?')->get();

$fake->assertCalled();
$fake->assertCalledWith('What is 2+2?');
```

### Embeddings::fake()

```php
use Cognesy\Instructor\Laravel\Facades\Embeddings;

$fake = Embeddings::fake([
    'hello' => [0.1, 0.2, 0.3, /* ... */],
]);

$embedding = Embeddings::withInputs('hello world')->first();

$fake->assertCalled();
$fake->assertCalledWith('hello world');
```

## Artisan Commands

### Install

```bash
php artisan instructor:install
```

Publishes configuration and checks API key setup.

### Test Connection

```bash
php artisan instructor:test
php artisan instructor:test --preset=anthropic
php artisan instructor:test --inference
```

Verifies your API configuration is working.

### Generate Response Model

```bash
# Basic response model
php artisan make:response-model PersonData

# Collection response model
php artisan make:response-model ProductList --collection

# Nested objects response model
php artisan make:response-model CompanyProfile --nested

# With description
php artisan make:response-model Invoice --description="Represents an invoice extracted from PDF"
```

## Events

Instructor dispatches events that you can listen to:

### Listening to Events

```php
// In EventServiceProvider
protected $listen = [
    \Cognesy\Instructor\Events\ExtractionComplete::class => [
        \App\Listeners\LogExtraction::class,
    ],
];
```

### Available Events

- `ExtractionStarted` - Extraction begins
- `ExtractionComplete` - Extraction finished successfully
- `ExtractionFailed` - Extraction failed with error
- `InferenceRequested` - LLM call initiated
- `InferenceComplete` - LLM call finished
- `ValidationFailed` - Response validation failed
- `RetryAttempted` - Retry is being attempted

### Event Bridge Configuration

```php
// config/instructor.php
'events' => [
    'dispatch_to_laravel' => true,

    // Only bridge specific events (empty = all events)
    'bridge_events' => [
        \Cognesy\Instructor\Events\ExtractionComplete::class,
        \Cognesy\Instructor\Events\ExtractionFailed::class,
    ],
],
```

## Logging

The package integrates with Laravel's logging system:

```php
// config/instructor.php
'logging' => [
    'enabled' => true,
    'channel' => env('INSTRUCTOR_LOG_CHANNEL', 'stack'),
    'preset' => env('INSTRUCTOR_LOG_PRESET', 'default'),
],
```

### Logging Presets

- `default` - Full logging with request/response details
- `production` - Minimal logging for production (no sensitive data)
- `custom` - Define your own logging pipeline

### Custom Logging

```php
'logging' => [
    'enabled' => true,
    'preset' => 'custom',
    'custom' => [
        'filters' => ['request_time' => ['min' => 1000]],
        'enrichers' => ['timestamp', 'request_id'],
        'formatters' => ['json'],
        'writers' => [['type' => 'log', 'channel' => 'llm']],
    ],
],
```

## HTTP Client Integration

The package uses Laravel's HTTP client, enabling `Http::fake()` for testing:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => '{"name":"John","age":30}']]],
    ]),
]);

// Your StructuredOutput calls will use the fake HTTP response
```

## Dependency Injection

Inject services directly into your classes:

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

    public function extractPerson(string $text): PersonData
    {
        return $this->structuredOutput
            ->with(messages: $text, responseModel: PersonData::class)
            ->get();
    }
}
```

## Caching

Enable response caching for repeated extractions:

```php
// config/instructor.php
'cache' => [
    'enabled' => true,
    'store' => env('INSTRUCTOR_CACHE_STORE', 'redis'),
    'ttl' => 3600, // 1 hour
    'prefix' => 'instructor:',
],
```

## Advanced Usage

### Streaming

```php
$stream = StructuredOutput::with(
    messages: 'Extract a long document...',
    responseModel: DocumentData::class,
)->withStreaming()->stream();

foreach ($stream->partials() as $partial) {
    // Handle partial updates
    echo $partial->title ?? 'Loading...';
}

$final = $stream->final();
```

### Retries and Validation

```php
$data = StructuredOutput::with(
    messages: 'Extract with validation...',
    responseModel: MyValidatedModel::class,
    maxRetries: 3,
)->get();
```

### Custom Validators

```php
$data = StructuredOutput::with(
    messages: 'Extract...',
    responseModel: MyModel::class,
)
->withValidators(MyCustomValidator::class)
->get();
```

## Package Structure

```
packages/laravel/
├── config/
│   └── instructor.php          # Main configuration
├── src/
│   ├── Console/
│   │   ├── InstructorInstallCommand.php
│   │   ├── InstructorTestCommand.php
│   │   └── MakeResponseModelCommand.php
│   ├── Events/
│   │   ├── InstructorEventBridge.php
│   │   └── InstructorEventServiceProvider.php
│   ├── Facades/
│   │   ├── Embeddings.php
│   │   ├── Inference.php
│   │   └── StructuredOutput.php
│   ├── Support/
│   │   └── LaravelConfigProvider.php
│   ├── Testing/
│   │   ├── EmbeddingsFake.php
│   │   ├── InferenceFake.php
│   │   └── StructuredOutputFake.php
│   └── InstructorServiceProvider.php
└── resources/
    └── stubs/
        ├── response-model.stub
        ├── response-model.collection.stub
        └── response-model.nested.stub
```

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x
- `cognesy/instructor` package

## License

MIT License. See [LICENSE](LICENSE) for details.
