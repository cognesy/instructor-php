---
title: Usage
description: 'The day-to-day API for structured output.'
---

## The Standard Flow

Most usage starts with `StructuredOutput`, a response model, and one call to `get()`.

```php
use Cognesy\Instructor\StructuredOutput;

final class Person {
    public string $name;
    public int $age;
}

$person = (new StructuredOutput)
    ->with(
        messages: 'Jason is 28 years old.',
        responseModel: Person::class,
    )
    ->get();
// @doctest id="7d4f"
```

## Build The Request

Use `with(...)` for the common path, or the fluent methods when you want to be explicit.

Common request methods:

- `withMessages(...)`
- `withInput(...)`
- `withResponseModel(...)`
- `withSystem(...)`
- `withPrompt(...)`
- `withExamples(...)`
- `withModel(...)`
- `withOptions(...)`
- `withStreaming(...)`

## Read The Result

- `get()` returns the parsed value
- `response()` returns `StructuredOutputResponse`
- `rawResponse()` returns the underlying inference response
- `stream()` returns `StructuredOutputStream`
- `create()` returns `PendingStructuredOutput` for lazy execution

## Use A Runtime When Behavior Matters

Keep provider and runtime behavior in `StructuredOutputRuntime`:

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$runtime = StructuredOutputRuntime::fromConfig(
    LLMConfig::fromPreset('openai')
)->withMaxRetries(2);

$result = (new StructuredOutput)
    ->withRuntime($runtime)
    ->with(messages: 'Jason is 28 years old.', responseModel: Person::class)
    ->get();
// @doctest id="58d7"
```

## Use `create()` When You Need Control

`create()` gives you a lazy handle. Nothing is executed until you read from it.

```php
$pending = (new StructuredOutput)
    ->with(messages: 'Jason is 28 years old.', responseModel: Person::class)
    ->create();

$person = $pending->get();
// @doctest id="ae0f"
```
