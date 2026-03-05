# Instructor Package Cheatsheet

Code-verified quick reference for `packages/instructor`.

## Core Flow

```php
use Cognesy\Instructor\StructuredOutput;

class User {
    public string $name;
    public int $age;
}

$user = (new StructuredOutput)
    ->with(
        messages: 'Jason is 28 years old.',
        responseModel: User::class,
    )
    ->get();
```

## Create `StructuredOutput`

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$so = new StructuredOutput();
$so = StructuredOutput::fromLLMConfig(LLMConfig::fromArray(['driver' => 'openai']));
```

## Request Configuration (`StructuredOutput`)

```php
$so = (new StructuredOutput)
    ->withConfig($config)
    ->withMessages($messages)
    ->withInput($input)
    ->withRequest($request)
    ->withResponseModel(User::class)
    ->withResponseClass(User::class)
    ->withResponseObject(new User())
    ->withResponseJsonSchema($jsonSchema)
    ->withSystem('You are a precise extractor')
    ->withPrompt('Extract user profile')
    ->withExamples($examples)
    ->withModel('gpt-4o-mini')
    ->withOptions(['temperature' => 0])
    ->withOption('max_tokens', 1200)
    ->withStreaming(true);
```

Single-call variant:

```php
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$so = (new StructuredOutput)->with(
    messages: $messages,
    responseModel: User::class,
    system: '...',
    prompt: '...',
    examples: $examples,
    model: 'gpt-4o-mini',
    maxRetries: 2,
    options: ['temperature' => 0],
    mode: OutputMode::Json,
);
```

## Runtime / Provider Setup

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$so = StructuredOutput::fromLLMConfig(LLMConfig::fromArray(['driver' => 'openai']));

$runtime = StructuredOutputRuntime::fromDsn('driver=openai,model=gpt-4o-mini');
$so = (new StructuredOutput)->withRuntime($runtime);
```

## Pipeline Overrides

```php
$so = (new StructuredOutput)
    ->withValidators(...$validators)
    ->withTransformers(...$transformers)
    ->withDeserializers(...$deserializers)
    ->withExtractors(...$extractors);
```

## Execution

```php
$result = $so->get();          // parsed value
$response = $so->response();   // InferenceResponse
$stream = $so->stream();       // StructuredOutputStream

$pending = $so->create();
$result = $pending->get();
$response = $pending->response();
$stream = $pending->stream();
$array = $pending->toArray();
$json = $pending->toJson();
$execution = $pending->execution();
```

Type helpers (available on `StructuredOutput` and `PendingStructuredOutput`):

```php
$so->getString();
$so->getInt();
$so->getFloat();
$so->getBoolean();
$so->getObject();
$so->getArray();
```

## Streaming (`StructuredOutputStream`)

```php
$stream = $so->withStreaming()->stream();

foreach ($stream->partials() as $partial) {
    // every parsed partial update
}

foreach ($stream->sequence() as $sequenceUpdate) {
    // one update per completed sequence item
}

foreach ($stream->responses() as $responseUpdate) {
    // PartialInferenceResponse | InferenceResponse
}

$latestValue = $stream->lastUpdate();
$latestResponse = $stream->lastResponse();
$usage = $stream->usage();
$finalValue = $stream->finalValue();
$finalResponse = $stream->finalResponse();
```

## Response Model Helpers

### `Sequence`

```php
use Cognesy\Instructor\Extras\Sequence\Sequence;

$people = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: Sequence::of(Person::class),
    )
    ->get();

$count = $people->count();
$first = $people->first();
$last = $people->last();
$item = $people->get(0);
$all = $people->all();
```

### `Scalar`

```php
use Cognesy\Instructor\Extras\Scalar\Scalar;

$name = (new StructuredOutput)
    ->with(messages: $text, responseModel: Scalar::string('name'))
    ->get();

$age = (new StructuredOutput)
    ->with(messages: $text, responseModel: Scalar::integer('age'))
    ->get();

$isAdult = (new StructuredOutput)
    ->with(messages: $text, responseModel: Scalar::boolean('isAdult'))
    ->get();
```

### `Maybe`

```php
use Cognesy\Instructor\Extras\Maybe\Maybe;

$maybeUser = (new StructuredOutput)
    ->with(messages: $text, responseModel: Maybe::is(User::class))
    ->get();

if ($maybeUser->hasValue()) {
    $user = $maybeUser->get();
}

$error = $maybeUser->error();
```

## Output Controls

```php
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

$so = (new StructuredOutput)
    ->withOutputMode(OutputMode::Json)
    ->withMaxRetries(3)
    ->withDefaultToStdClass(true);

$asArray = (new StructuredOutput)->intoArray();
$asClass = (new StructuredOutput)->intoInstanceOf(User::class);
$asObject = (new StructuredOutput)->intoObject(Scalar::integer('rating'));
```

## Cached Context

```php
$result = (new StructuredOutput)
    ->withCachedContext(
        messages: $longContext,
        system: 'You know the full context',
    )
    ->with(
        prompt: 'Extract only contact details',
        responseModel: Contact::class,
    )
    ->get();
```

## Examples API

```php
use Cognesy\Instructor\Extras\Example\Example;

$result = (new StructuredOutput)
    ->withExamples([
        Example::fromText(
            'John Doe, john@example.com',
            ['name' => 'John Doe', 'email' => 'john@example.com'],
        ),
    ])
    ->with(messages: $text, responseModel: Contact::class)
    ->get();
```

## Events

```php
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;

$result = (new StructuredOutput)
    ->onEvent(StructuredOutputRequestReceived::class, function (object $event): void {
        // handle selected event
    })
    ->wiretap(function (object $event): void {
        // handle all events
    })
    ->with(messages: $text, responseModel: User::class)
    ->get();
```
