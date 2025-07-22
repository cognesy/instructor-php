## Basic usage

This is a simple example demonstrating how Instructor retrieves structured information from provided text (or chat message sequence).

Response model class is a plain PHP class with typehints specifying the types of fields of the object.

> NOTE: By default, Instructor looks for OPENAI_API_KEY environment variable to get
> your API key. You can also provide the API key explicitly when creating the
> Instructor instance.

```php
<?php
use Cognesy\Instructor\StructuredOutput;

// Step 0: Create .env file in your project root:
// OPENAI_API_KEY=your_api_key

// Step 1: Define target data structure(s)
class Person {
    public string $name;
    public int $age;
}

// Step 2: Provide content to process
$text = "His name is Jason and he is 28 years old.";

// Step 3: Use Instructor to run LLM inference
$person = (new StructuredOutput)
    ->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Person::class,
    )
    ->get();

// Step 4: Work with structured response data
assert($person instanceof Person); // true
assert($person->name === 'Jason'); // true
assert($person->age === 28); // true

echo $person->name; // Jason
echo $person->age; // 28

var_dump($person);
// Person {
//     name: "Jason",
//     age: 28
// }
?>
```

!!! note

    Instructor supports classes/objects as response models, as well as specialized helper classes like `Scalar` for simple values, `Maybe` for optional data, `Sequence` for arrays, and `Structure` for dynamically defined schemas.



## Fluent API Methods

StructuredOutput provides a comprehensive fluent API for configuring requests:

### Request Configuration
```php
$structuredOutput = (new StructuredOutput)
    ->withMessages($messages)           // Set chat messages
    ->withInput($input)                 // Set input (converted to messages)
    ->withSystem($systemPrompt)         // Set system prompt
    ->withPrompt($prompt)               // Set additional prompt
    ->withExamples($examples)           // Set example data
    ->withModel($modelName)             // Set LLM model
    ->withOptions($options)             // Set LLM options
    ->withStreaming(true)               // Enable streaming
```

### Response Model Configuration
```php
$structuredOutput = (new StructuredOutput)
    ->withResponseModel($model)         // Set response model (class/object/array)
    ->withResponseClass($className)     // Set response class specifically
    ->withResponseObject($object)       // Set response object instance
    ->withResponseJsonSchema($schema)   // Set JSON schema directly
```

### Configuration and Behavior
```php
$structuredOutput = (new StructuredOutput)
    ->withMaxRetries(3)                 // Set retry count
    ->withOutputMode($mode)             // Set output mode
    ->withToolName($name)               // Set tool name for Tools mode
    ->withToolDescription($desc)        // Set tool description
    ->withRetryPrompt($prompt)          // Set retry prompt
    ->withConfig($config)               // Set configuration object
    ->withConfigPreset($preset)         // Use configuration preset
```

### LLM Provider Configuration
```php
$structuredOutput = (new StructuredOutput)
    ->using($preset)                    // Use LLM preset (e.g., 'openai')
    ->withDsn($dsn)                     // Set connection DSN
    ->withLLMProvider($provider)        // Set custom LLM provider
    ->withLLMConfig($config)            // Set LLM configuration
    ->withDriver($driver)               // Set inference driver
    ->withHttpClient($client)           // Set HTTP client
```

### Processing Overrides
```php
$structuredOutput = (new StructuredOutput)
    ->withValidators(...$validators)    // Override validators
    ->withTransformers(...$transformers) // Override transformers
    ->withDeserializers(...$deserializers) // Override deserializers
```


## Request Execution Methods

After configuring your `StructuredOutput` instance, you have several ways to execute the request and access different types of responses:

### Direct Execution Methods

```php
<?php
use Cognesy\Instructor\StructuredOutput;

$structuredOutput = (new StructuredOutput)->with(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
);

// Get structured result directly
$person = $structuredOutput->get();

// Get raw LLM response
$llmResponse = $structuredOutput->response();

// Get streaming interface
$stream = $structuredOutput->stream();
?>
```

### Pending Execution with `create()`

The `create()` method returns a `PendingStructuredOutput` instance, which acts as an execution handler that provides the same access methods:

```php
<?php
$pending = $structuredOutput->create();

// Execute and get structured result
$person = $pending->get();

// Execute and get raw LLM response
$llmResponse = $pending->response();

// Execute and get streaming interface
$stream = $pending->stream();

// Additional utility methods
$json = $pending->toJson();      // Convert result to JSON string
$array = $pending->toArray();    // Convert result to array
$jsonObj = $pending->toJsonObject(); // Convert result to Json object
?>
```

### Response Types Explained

- **`get()`**: Returns the parsed and validated structured result (e.g., `Person` object)
- **`response()`**: Returns the raw LLM response object with metadata like tokens, model info, etc.
- **`stream()`**: Returns `StructuredOutputStream` for real-time processing of streaming responses

The `PendingStructuredOutput` class serves as a flexible execution interface that lets you choose how to process the LLM response based on your specific needs.


## String as Input

You can provide a string instead of an array of messages. This is useful when you want to extract data from a single block of text and want to keep your code simple.

```php
<?php
use Cognesy\Instructor\StructuredOutput;

$value = (new StructuredOutput)
    ->with(
        messages: "His name is Jason, he is 28 years old.",
        responseModel: Person::class,
    )
    ->get();
?>
```


### Structured-to-structured data processing

Instructor offers a way to use structured data as an input. This is
useful when you want to use object data as input and get another object
with a result of LLM inference.

The `input` field of Instructor's `with()` method
can be an object, but also an array or just a string.

```php
<?php
use Cognesy\Instructor\StructuredOutput;

class Email {
    public function __construct(
        public string $address = '',
        public string $subject = '',
        public string $body = '',
    ) {}
}

$email = new Email(
    address: 'joe@gmail',
    subject: 'Status update',
    body: 'Your account has been updated.'
);

$translation = (new StructuredOutput)->with(
        input: $email,
        responseModel: Email::class,
        prompt: 'Translate the text fields of email to Spanish. Keep other fields unchanged.',
    )
    ->get();
?>
```



## Streaming support

Instructor supports streaming of partial results, allowing you to start
processing the data as soon as it is available.

```php
<?php
use Cognesy\Instructor\StructuredOutput;

$stream = (new StructuredOutput)->with(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
    options: ['stream' => true]
)->stream();

foreach ($stream->partials() as $partialPerson) {
    // process partial person data
    echo "Name: " $partialPerson->name ?? '...';
    echo "Age: " $partialPerson->age ?? '...';
}

// after streaming is done you can get the final, fully processed person object...
$person = $stream->lastUpdate()
// ...to, for example, save it to the database
$db->save($person);
?>
```


## Scalar responses

See [Scalar responses](/essentials/scalars) for more information on how to generate scalar responses with `Scalar` adapter class.


## Partial responses and streaming

See [Streaming and partial updates](/advanced/partials) for more information on how to work with partial updates and streaming.


## Extracting arguments for function call

See [FunctionCall helper class](/advanced/function_calls) for more information on how to extract arguments for callable objects.


## Execution Methods Summary

Once configured, you can execute your request using different methods depending on your needs:

```php
// Direct execution methods
$result = $structuredOutput->get();       // Get structured result
$response = $structuredOutput->response(); // Get raw LLM response  
$stream = $structuredOutput->stream();     // Get streaming interface

// Or use create() to get PendingStructuredOutput for flexible execution
$pending = $structuredOutput->create();
$result = $pending->get();                 // Same methods available
$json = $pending->toJson();               // Plus utility methods
```

- **`get()`**: Returns the parsed and validated structured result
- **`response()`**: Returns the raw LLM response with metadata
- **`stream()`**: Returns `StructuredOutputStream` for real-time processing
- **`create()`**: Returns `PendingStructuredOutput` for flexible execution control
