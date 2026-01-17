---
title: 'Features'
---


A comprehensive overview of Instructor's capabilities.

## Core Features

### Structured Output Extraction

Define a PHP class, get a populated object back:

```php
<?php
class Person {
    public string $name;
    public int $age;
    public string $occupation;
}

$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withMessages("Extract: Sarah, 32, software architect")
    ->get();
```

**Key capabilities:**

- Works with any PHP class with typed properties
- Supports nested objects and arrays
- Handles nullable fields gracefully
- Preserves type information throughout

### Automatic Validation

Built-in support for Symfony Validator:

```php
<?php
use Symfony\Component\Validator\Constraints as Assert;

class User {
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\Range(min: 18, max: 120)]
    public int $age;

    #[Assert\Choice(['active', 'inactive', 'pending'])]
    public string $status;
}
```

**Validation features:**

- All Symfony validation constraints supported
- Custom validators work out of the box
- Validation errors trigger automatic retry
- Error messages sent to LLM for self-correction

### Self-Correcting Retries

When validation fails, Instructor automatically retries:

```php
<?php
$result = (new StructuredOutput)
    ->withResponseClass(User::class)
    ->withMessages($text)
    ->withMaxRetries(3)
    ->get();
```

**Retry behavior:**

1. LLM generates response
2. Response validated against constraints
3. On failure: errors sent back to LLM with context
4. LLM attempts correction
5. Repeat until valid or max retries reached

---

## Input Flexibility

### Text Input

Simple string input:

```php
<?php
->withMessages("John is 25 years old and works at Acme Corp")
```

### Chat Messages

OpenAI-style message arrays:

```php
<?php
->withMessages([
    ['role' => 'system', 'content' => 'You are a data extraction expert.'],
    ['role' => 'user', 'content' => 'Extract the person: John, 25, engineer']
])
```

### Image Input

Process images with vision-capable models:

```php
<?php
->withImages(['path/to/image.jpg'])
->withMessages("Extract all text from this document")
```

**Supported formats:** JPEG, PNG, GIF, WebP

### Structured Input

Pass objects or arrays as input:

```php
<?php
$inputData = [
    'document' => $documentText,
    'metadata' => ['source' => 'email', 'date' => '2024-01-15']
];

$result = (new StructuredOutput)
    ->withResponseClass(Analysis::class)
    ->withInput($inputData)
    ->get();
```

---

## Output Modes

### Tools Mode (Default)

Uses LLM function/tool calling:

```php
<?php
->withOutputMode(OutputMode::Tools)
```

Best for: OpenAI, Anthropic, most modern models

### JSON Schema Mode

Strict schema enforcement:

```php
<?php
->withOutputMode(OutputMode::JsonSchema)
```

Best for: GPT-4, models with strict JSON Schema support

### JSON Mode

Basic JSON response format:

```php
<?php
->withOutputMode(OutputMode::Json)
```

Best for: Models supporting JSON mode without strict schemas

### Markdown JSON Mode

Prompting-based extraction:

```php
<?php
->withOutputMode(OutputMode::MdJson)
```

Best for: Models without JSON mode, fallback option

---

## Response Types

### Single Object

```php
<?php
$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->get();
```

### Arrays of Objects

Use `Sequence::of()` to extract lists:

```php
<?php
use Cognesy\Instructor\Extras\Sequence\Sequence;

$people = (new StructuredOutput)
    ->withResponseClass(Sequence::of(Person::class))
    ->withMessages($text)
    ->get();

// Iterate over results
foreach ($people as $person) {
    echo $person->name;
}

// Or use array-like access
$first = $people->first();
$count = $people->count();
$all = $people->toArray();
```

### Scalar Values

Extract simple types with adapters:

```php
<?php
use Cognesy\Instructor\Extras\Scalars\Scalar;

// Boolean
$isSpam = (new StructuredOutput)
    ->withResponseClass(Scalar::boolean('isSpam'))
    ->get();

// Integer
$count = (new StructuredOutput)
    ->withResponseClass(Scalar::integer('count'))
    ->get();

// String
$summary = (new StructuredOutput)
    ->withResponseClass(Scalar::string('summary'))
    ->get();
```

### Enums

```php
<?php
enum Sentiment: string {
    case Positive = 'positive';
    case Negative = 'negative';
    case Neutral = 'neutral';
}

$sentiment = (new StructuredOutput)
    ->withResponseClass(Scalar::enum(Sentiment::class, 'sentiment'))
    ->get();
```

---

## Streaming

### Partial Updates

Get incremental results as they arrive:

```php
<?php
$stream = (new StructuredOutput)
    ->withResponseClass(Article::class)
    ->with(
        messages: $text,
        options: ['stream' => true]
    )
    ->stream();

foreach ($stream->partials() as $partial) {
    // $partial has incrementally populated fields
    updateUI($partial);
}

$final = $stream->finalValue();
```

Or use the callback approach:

```php
<?php
$article = (new StructuredOutput)
    ->withResponseClass(Article::class)
    ->onPartialUpdate(fn($partial) => updateUI($partial))
    ->with(
        messages: $text,
        options: ['stream' => true]
    )
    ->get();
```

### Sequence Streaming

Stream sequence items as they complete:

```php
<?php
use Cognesy\Instructor\Extras\Sequence\Sequence;

$list = (new StructuredOutput)
    ->onSequenceUpdate(fn($seq) => processComplete($seq->last()))
    ->withResponseClass(Sequence::of(Person::class))
    ->with(
        messages: $text,
        options: ['stream' => true]
    )
    ->get();
```

---

## LLM Providers

### Supported Providers

| Provider | API Type | Streaming | Vision | Tool Calling |
|----------|----------|:---------:|:------:|:------------:|
| OpenAI | Native | ✓ | ✓ | ✓ |
| Anthropic | Native | ✓ | ✓ | ✓ |
| Google Gemini | Native | ✓ | ✓ | ✓ |
| Azure OpenAI | OpenAI-compatible | ✓ | ✓ | ✓ |
| Mistral | Native | ✓ | - | ✓ |
| Cohere | OpenAI-compatible | ✓ | - | ✓ |
| Groq | OpenAI-compatible | ✓ | - | ✓ |
| Fireworks AI | OpenAI-compatible | ✓ | ✓ | ✓ |
| Together AI | OpenAI-compatible | ✓ | ✓ | ✓ |
| Ollama | OpenAI-compatible | ✓ | ✓ | ✓ |
| OpenRouter | OpenAI-compatible | ✓ | ✓ | ✓ |
| Perplexity | OpenAI-compatible | ✓ | - | - |
| DeepSeek | OpenAI-compatible | ✓ | - | ✓ |
| xAI (Grok) | OpenAI-compatible | ✓ | - | ✓ |
| Cerebras | OpenAI-compatible | ✓ | - | ✓ |
| SambaNova | OpenAI-compatible | ✓ | - | ✓ |

### Provider Selection

```php
<?php
// Use preset from config
->using('anthropic')

// Or configure inline
->withConfig(new LLMConfig(
    apiUrl: 'https://api.example.com',
    apiKey: $apiKey,
    model: 'model-name'
))
```

---

## Schema Definition

### Type-Hinted Classes

```php
<?php
class Order {
    public string $orderId;
    public Customer $customer;
    /** @var LineItem[] */
    public array $items;
    public float $total;
    public string|null $notes;
}
```

### PHP DocBlocks for Instructions

```php
<?php
class Product {
    /** The product SKU, e.g., "SKU-12345" */
    public string $sku;

    /** Price in USD, without currency symbol */
    public float $price;

    /** @var string[] List of applicable categories */
    public array $categories;
}
```

### Attributes for Detailed Control

```php
<?php
use Cognesy\Instructor\Schema\Attributes\Description;
use Cognesy\Instructor\Schema\Attributes\Instructions;

class Analysis {
    #[Description("Sentiment score from -1.0 (negative) to 1.0 (positive)")]
    public float $sentiment;

    #[Instructions("Extract the 3 most important points only")]
    /** @var string[] */
    public array $keyPoints;
}
```

### Dynamic Schemas with Structure

```php
<?php
use Cognesy\Instructor\Extras\Structure\Structure;

$schema = Structure::define('user', [
    Structure::string('name'),
    Structure::int('age'),
    Structure::array('tags', Structure::string('tag')),
]);

$result = (new StructuredOutput)
    ->withResponseClass($schema)
    ->get();
```

---

## Advanced Features

### Context Caching

Reduce costs with cached context (Anthropic):

```php
<?php
->withCachedContext([
    'Large document or context here...',
    'This won\'t be re-sent on retries'
])
```

### Custom Prompts

Override default extraction prompts:

```php
<?php
->withPrompt("Extract the following fields precisely: ...")
->withRetryPrompt("The previous attempt had errors: {errors}. Please correct.")
```

### Event System

Monitor internal processing:

```php
<?php
use Cognesy\Instructor\Events;

$instructor = new StructuredOutput();

$instructor->onEvent(Events\RequestSent::class, function($event) {
    logger()->info('Request sent', $event->toArray());
});

$instructor->onEvent(Events\ResponseReceived::class, function($event) {
    logger()->info('Response received', $event->toArray());
});
```

### Debug Mode

See all LLM interactions:

```php
<?php
->withDebug(true)
```

Outputs:
- Full request payloads
- Raw LLM responses
- Validation errors
- Retry attempts

---

## Framework Integration

### Laravel

```php
<?php
// Service provider auto-registers

// Use facade
use Cognesy\Instructor\Facades\Instructor;

$result = Instructor::respond()
    ->withResponseClass(Person::class)
    ->withMessages($text)
    ->get();

// Or inject
public function handle(StructuredOutput $instructor)
{
    return $instructor->withResponseClass(Person::class)->get();
}
```

### Symfony

```php
<?php
// Configure as service
// services.yaml
services:
    Cognesy\Instructor\StructuredOutput:
        autowire: true

// Use in controller
public function extract(StructuredOutput $instructor): Response
{
    $result = $instructor->withResponseClass(Person::class)->get();
    return $this->json($result);
}
```

### Standalone

```php
<?php
// No framework needed
require 'vendor/autoload.php';

$result = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withMessages($text)
    ->get();
```

---

## Observability

### Token Usage

```php
<?php
$response = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withMessages($text)
    ->getResponse();

echo $response->usage->inputTokens;
echo $response->usage->outputTokens;
echo $response->usage->totalTokens;
```

### Timing

```php
<?php
echo $response->timing->total; // Total processing time
```

### Event-Based Logging

```php
<?php
$instructor->onEvent('*', function($event) {
    $logger->log($event->name(), $event->toArray());
});
```

---

## What's Next

- **[Getting Started](getting-started)** - Quick installation guide
- **[Why Instructor](why-instructor)** - Understanding the value proposition
- **[Use Cases](use-cases)** - Industry-specific examples
- **[Cookbook](/cookbook)** - 60+ working examples
- **[API Reference](/packages/instructor/introduction)** - Complete documentation
