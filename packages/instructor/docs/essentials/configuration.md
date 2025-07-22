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
    ->withRetryPrompt($prompt)          // Set custom retry prompt for validation failures
    ->withSchemaName($name)             // Set schema name for documentation
    ->withToolName($name)               // Set tool name for Tools mode
    ->withToolDescription($description) // Set tool description for Tools mode
```

## Advanced Configuration

Fine-tune Instructor's internal processing:

```php
$structuredOutput = (new StructuredOutput)
    ->withConfig($configObject)         // Use custom StructuredOutputConfig instance
    ->withConfigPreset($presetName)     // Use predefined configuration preset
    ->withConfigProvider($provider)     // Use custom configuration provider
    ->withObjectReferences(true)        // Enable object reference handling
    ->withDefaultToStdClass(true)       // Default to stdClass for unknown types
    ->withDeserializationErrorPrompt($prompt) // Custom deserialization error prompt
    ->withThrowOnTransformationFailure(true)  // Throw on transformation failures
```

## LLM Provider Configuration

Configure connection and communication with LLM providers:

```php
$structuredOutput = (new StructuredOutput)
    ->using($preset)                    // Use LLM preset (e.g., 'openai', 'anthropic')
    ->withDsn($dsn)                     // Set connection DSN
    ->withLLMProvider($provider)        // Set custom LLM provider instance
    ->withLLMConfig($config)            // Set LLM configuration object
    ->withLLMConfigOverrides($overrides) // Override specific LLM config values
    ->withDriver($driver)               // Set custom inference driver
    ->withHttpClient($client)           // Set custom HTTP client
    ->withHttpClientPreset($preset)     // Use HTTP client preset
    ->withDebugPreset($preset)          // Enable debug preset
    ->withClientInstance($driverName, $instance) // Set client instance for specific driver
```

## Processing Pipeline Overrides

Customize validation, transformation, and deserialization:

```php
$structuredOutput = (new StructuredOutput)
    ->withValidators(...$validators)    // Override response validators
    ->withTransformers(...$transformers) // Override response transformers  
    ->withDeserializers(...$deserializers) // Override response deserializers
```

## Event Handling

Configure real-time processing callbacks:

```php
$structuredOutput = (new StructuredOutput)
    ->onPartialUpdate($callback)        // Handle partial response updates during streaming
    ->onSequenceUpdate($callback)       // Handle sequence item completion during streaming
```

## Configuration Examples

### Basic OpenAI Configuration
```php
$result = (new StructuredOutput)
    ->using('openai')
    ->withModel('gpt-4')
    ->withMaxRetries(3)
    ->withMessages("Extract person data from: John is 25 years old")
    ->withResponseClass(Person::class)
    ->get();
```

### Streaming with Callbacks
```php
$result = (new StructuredOutput)
    ->using('openai')
    ->withStreaming(true)
    ->onPartialUpdate(fn($partial) => updateUI($partial))
    ->withMessages("Generate a list of tasks")
    ->withResponseClass(Sequence::of(Task::class))
    ->get();
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