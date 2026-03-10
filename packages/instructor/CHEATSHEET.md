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
$so = StructuredOutput::using('openai');
$so = StructuredOutput::fromConfig(LLMConfig::fromArray(['driver' => 'openai']));
```

## Request Configuration (`StructuredOutput`)

```php
$so = (new StructuredOutput)
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
$so = (new StructuredOutput)->with(
    messages: $messages,
    responseModel: User::class,
    system: '...',
    prompt: '...',
    examples: $examples,
    model: 'gpt-4o-mini',
    options: ['temperature' => 0],
);
```

## Runtime / Provider Setup

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Instructor\Enums\OutputMode;

$so = StructuredOutput::fromConfig(LLMConfig::fromArray(['driver' => 'openai']));

$runtime = StructuredOutputRuntime::fromConfig(LLMConfig::fromDsn('driver=openai,model=gpt-4o-mini'))
    ->withOutputMode(OutputMode::Json)
    ->withMaxRetries(2);
$so = (new StructuredOutput)->withRuntime($runtime);
```

## Pipeline Overrides

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$runtime = StructuredOutputRuntime::fromConfig(LLMConfig::fromDsn('driver=openai,model=gpt-4o-mini'))
    ->withValidators($validators)
    ->withTransformers($transformers)
    ->withDeserializers($deserializers)
    ->withExtractors($extractors);

$so = (new StructuredOutput)->withRuntime($runtime);
```

## Execution

```php
$result = $so->get();          // parsed value
$response = $so->response();   // StructuredOutputResponse
$raw = $so->inferenceResponse(); // InferenceResponse
$stream = $so->stream();       // StructuredOutputStream

$pending = $so->create();
$result = $pending->get();
$response = $pending->response();
$raw = $pending->inferenceResponse();
$stream = $pending->stream();
$array = $pending->toArray();
$json = $pending->toJson();
$jsonObject = $pending->toJsonObject();
$execution = $pending->execution();
```

`PendingStructuredOutput` is a lazy handle:
- no provider call happens until one of the read methods above is used
- `get()`, `response()`, `inferenceResponse()`, and `stream()` coordinate one execution
- mutable lifecycle bookkeeping sits behind the internal execution session, not on the facade-facing handle
- long-lived streaming state stays in the dedicated stream/state objects

Type helpers (available on `StructuredOutput` and `PendingStructuredOutput`):

```php
$so->getString();
$so->getInt();
$so->getFloat();
$so->getBoolean();
$so->getObject();
$so->getArray();
$so->getInstanceOf(User::class);
```

## Streaming (`StructuredOutputStream`)

```php
$stream = $so->withStreaming()->stream();

foreach ($stream->partials() as $partial) {
    // every parsed partial update
}

foreach ($stream->sequence() as $sequenceUpdate) {
    // one update per completed sequence item (Sequence responses only)
}

foreach ($stream->responses() as $responseUpdate) {
    // StructuredOutputResponse, partial or final
}

foreach ($stream->getIterator() as $rawUpdate) {
    // raw emitted StructuredOutputResponse snapshots
}

$latestValue = $stream->lastUpdate();
$latestResponse = $stream->lastResponse();
$usage = $stream->usage();
$finalValue = $stream->finalValue();
$finalResponse = $stream->finalResponse();
$finalRaw = $stream->finalInferenceResponse();
```

`lastResponse()` / `finalResponse()` return `StructuredOutputResponse`.
Use `->inferenceResponse()` when you need the nested raw `InferenceResponse`.

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
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$runtime = StructuredOutputRuntime::fromConfig(LLMConfig::fromDsn('driver=openai,model=gpt-4o-mini'))
    ->withOutputMode(OutputMode::Json)
    ->withMaxRetries(3)
    ->withDefaultToStdClass(true);

$so = (new StructuredOutput)->withRuntime($runtime);

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
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;
use Cognesy\Polyglot\Inference\LLMProvider;

$runtime = StructuredOutputRuntime::fromProvider(LLMProvider::new())
    ->onEvent(StructuredOutputRequestReceived::class, function (object $event): void {
        // handle selected event
    })
    ->wiretap(function (object $event): void {
        // handle all events
    });

$result = (new StructuredOutput)
    ->withRuntime($runtime)
    ->with(messages: $text, responseModel: User::class)
    ->get();
```

## Testing

Deterministic test seams:

- `Tests\Support\FakeInferenceDriver`
  - queue sync `InferenceResponse` fixtures or streaming `PartialInferenceDelta` batches
  - best for most unit and regression tests inside `packages/instructor`
- `Tests\MockHttp`
  - builds an HTTP client around `MockHttpDriver`
  - use when provider adapter and HTTP response shape still matter
- `Tests\Integration\Support\ProbeStreamDriver`
  - observation helper for streaming immediacy and call-count assertions
- `Tests\Integration\Support\ProbeIterator`
  - explicit iterator helper for controlled delta emission in integration tests
