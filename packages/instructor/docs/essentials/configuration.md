---
title: 'Configuration'
description: 'Configure Instructor behavior and processing options'
---

# Configuration Options

Instructor provides extensive configuration options through fluent API methods to customize its behavior, processing, and integration with various LLM providers.

## Request Configuration

Configure how Instructor processes your input and builds requests:

```php
$structuredOutput = (new StructuredOutput)
    ->withMessages($messages)           // Set chat messages
    ->withInput($input)                 // Set input (converted to messages)
    ->withSystem($systemPrompt)         // Set system prompt
    ->withPrompt($prompt)               // Set additional prompt
    ->withExamples($examples)           // Set example data for context
    ->withModel($modelName)             // Set LLM model name
    ->withOptions($options)             // Set LLM-specific options
    ->withOption($key, $value)          // Set individual LLM option
    ->withStreaming(true)               // Enable streaming responses
    ->withCachedContext($messages, $system, $prompt, $examples) // Use cached context
```

## Response Configuration

Define how Instructor should process and validate responses:

```php
$structuredOutput = (new StructuredOutput)
    ->withMaxRetries(3)                 // Set retry count for failed validations
    ->withOutputMode(OutputMode::Tools) // Set output mode (Tools, Json, JsonSchema, MdJson)
    ->withDefaultToStdClass(true)       // Fallback to stdClass for schema-less payloads
```

Stream replay policy is configured through `StructuredOutputConfig`:

```php
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

$structuredOutput = (new StructuredOutput)
    ->withConfig(new StructuredOutputConfig(
        responseCachePolicy: ResponseCachePolicy::None, // default in 2.0
    ));
```

Use `ResponseCachePolicy::Memory` if you need second-pass replay of streamed updates.

## Advanced Configuration

Fine-tune Instructor's internal processing:

```php
$structuredOutput = (new StructuredOutput)
    ->withConfig($configObject)         // Use custom StructuredOutputConfig instance
    ->withDefaultToStdClass(true)       // Default to stdClass for unknown types
```

## LLM Provider Configuration

Configure connection and communication with LLM providers:

```php
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

$structuredOutput = StructuredOutput::using('openai');

$structuredOutput = (new StructuredOutput)->withRuntime(
    StructuredOutputRuntime::fromDsn('preset=openai,model=gpt-4o-mini')
);

$provider = LLMProvider::using('openai')
    ->withLLMConfigOverrides(['temperature' => 0.2]);
$structuredOutput = (new StructuredOutput)->withRuntime(
    StructuredOutputRuntime::fromProvider(provider: $provider)
);
```

## Processing Pipeline Overrides

Customize validation, transformation, and deserialization:

```php
$structuredOutput = (new StructuredOutput)
    ->withValidators(...$validators)    // Override response validators
    ->withTransformers(...$transformers) // Override response transformers  
    ->withDeserializers(...$deserializers) // Override response deserializers
    ->withExtractors(...$extractors)    // Override response extractors
```

## Streaming Updates And Events

Handle incremental updates via stream iterators or event subscribers:

```php
$stream = (new StructuredOutput)
    ->withStreaming(true)
    ->withMessages("Generate a list of tasks")
    ->withResponseClass(Sequence::of(Task::class))
    ->stream();

foreach ($stream->partials() as $partial) {
    updateUI($partial);
}

foreach ($stream->sequence() as $sequence) {
    processItem($sequence->last());
}

$structuredOutput = (new StructuredOutput)
    ->onEvent(\Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated::class, $callback)
    ->onEvent(\Cognesy\Instructor\Events\Request\SequenceUpdated::class, $callback);
```

## Configuration Examples

### Basic OpenAI Configuration
```php
$result = StructuredOutput::using('openai')
    ->withModel('gpt-4')
    ->withMaxRetries(3)
    ->withMessages("Extract person data from: John is 25 years old")
    ->withResponseClass(Person::class)
    ->get();
```

### Streaming with partials()
```php
$stream = StructuredOutput::using('openai')
    ->withStreaming(true)
    ->withMessages("Generate a list of tasks")
    ->withResponseClass(Sequence::of(Task::class))
    ->stream();

foreach ($stream->partials() as $partial) {
    updateUI($partial);
}

$result = $stream->finalValue();
```

### Custom Configuration Object
```php
$config = new StructuredOutputConfig(
    maxRetries: 5,
    outputMode: OutputMode::JsonSchema,
    retryPrompt: "Please fix the validation errors and try again."
);

$result = (new StructuredOutput)
    ->withConfig($config)
    ->withMessages($input)
    ->withResponseClass(Person::class)
    ->get();
```
