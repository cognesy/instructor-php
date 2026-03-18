---
title: Overview
description: "The core concepts behind Instructor's structured output extraction."
---

Instructor is a library that turns LLM responses into typed, validated PHP data. It is
powered by Large Language Models and works with multiple providers out of the box.

Rather than parsing raw text or hand-rolling JSON extraction, you define a PHP class that
describes the shape of the data you want. Instructor handles the prompt construction,
the LLM call, deserialization, validation, and retries -- so the result that reaches
your code is always a typed object you can trust.

The library is inspired by [Instructor for Python](https://jxnl.github.io/instructor/)
created by Jason Liu.


## How It Works

The high-level flow is straightforward:

1. You describe the shape of the data you need (a response model).
2. You provide input text, chat messages, or even another object.
3. Instructor calls the LLM, extracts structured JSON, deserializes it into your model,
   validates the result, and retries if necessary.

```php
use Cognesy\Instructor\StructuredOutput;

final class User {
    public string $name;
    public int $age;
}

$user = (new StructuredOutput)
    ->with(
        messages: 'Jason is 25 years old.',
        responseModel: User::class,
    )
    ->get();

// $user->name === 'Jason'
// $user->age  === 25
// @doctest id="1784"
```

Behind the scenes, Instructor builds a JSON schema from the `User` class, instructs the
LLM to respond in that format, maps the JSON back into a `User` instance, and runs any
validation rules before returning the object.


## Core Concepts

Instructor keeps its model intentionally small. There are only a handful of concepts you
need to understand to be productive.


### Response Model

The response model defines the shape you want back from the LLM. It is the contract
between your code and the model.

Common choices:

- **A PHP class** -- the most typical approach. Instructor derives a JSON schema from the
  class's typed properties automatically.
- **A JSON schema array** -- useful when the shape is dynamic or defined at runtime.
- **Helper wrappers** -- `Scalar` for single values, `Sequence` for lists of objects, and
  `Maybe` for results that may not exist.

A well-designed response model is small, focused, and uses clear property names. Nested
objects and enums are fully supported.


### Request

A request combines your input with a response model and optional parameters. The
`StructuredOutput` class provides two equivalent styles for building one.

The compact style passes everything through `with()`:

```php
$user = (new StructuredOutput)
    ->with(
        messages: 'Jason is 25 years old.',
        responseModel: User::class,
        system: 'Extract accurate data.',
        model: 'gpt-4o',
    )
    ->get();
// @doctest id="9629"
```

The fluent style chains individual methods:

```php
$user = (new StructuredOutput)
    ->withMessages('Jason is 25 years old.')
    ->withResponseModel(User::class)
    ->withSystem('Extract accurate data.')
    ->withModel('gpt-4o')
    ->get();
// @doctest id="e87f"
```

Both produce identical requests. `StructuredOutput` is immutable -- every method returns
a new instance, so you can safely branch from a shared base.


### Runtime

`StructuredOutputRuntime` owns provider setup and runtime behavior. It holds the LLM
connection, retry policy, output mode, event dispatcher, and pipeline extension points
such as custom validators, transformers, deserializers, and extractors.

You typically create a runtime once and share it across many requests:

```php
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$runtime = StructuredOutputRuntime::fromConfig(
    LLMConfig::fromPreset('openai')
)->withMaxRetries(3);

$user = (new StructuredOutput)
    ->withRuntime($runtime)
    ->with(messages: 'Jason is 25 years old.', responseModel: User::class)
    ->get();
// @doctest id="6d89"
```

If you do not provide a runtime, `StructuredOutput` creates one from default settings
automatically.


### Execution

Execution is lazy. Calling `with()` or the fluent methods only builds a description of
the work. The LLM is not contacted until you read the result.

Three classes participate in execution:

| Class | Role |
|---|---|
| `StructuredOutput` | Builds the request and delegates to the runtime |
| `PendingStructuredOutput` | A lazy handle returned by `create()`. Execution starts when you call `get()`, `response()`, `stream()`, or any other read method |
| `StructuredOutputStream` | Handles streaming. Yields partial objects as the LLM generates tokens, then provides the final validated result |


### Validation and Retries

After the LLM responds, Instructor deserializes the JSON into your response model and
runs validation. If validation fails and retries are configured, Instructor sends the
validation errors back to the LLM and asks it to correct its response.

Validation uses Symfony validation attributes by default:

```php
use Symfony\Component\Validator\Constraints as Assert;

final class UserDetails {
    #[Assert\NotBlank]
    public string $name;

    #[Assert\Email]
    public string $email;
}

$user = (new StructuredOutput)
    ->withRuntime(
        StructuredOutputRuntime::fromDefaults()->withMaxRetries(2)
    )
    ->with(
        messages: 'You can reach me at jason@gmailcom -- Jason',
        responseModel: UserDetails::class,
    )
    ->get();

// If the LLM returns an invalid email on the first attempt,
// Instructor retries up to 2 more times to get a valid result.
// @doctest id="6d0a"
```

This self-correcting loop is one of Instructor's most powerful features. The LLM sees
exactly which fields failed and why, giving it a strong signal for the next attempt.


## Where To Go Next

- [Why Use Instructor?](why) -- the motivation behind a schema-first approach
- [Usage](../essentials/usage) -- the day-to-day API reference
- [Data Model](../essentials/data_model) -- choosing the right response model
- [Validation](../essentials/validation) -- validation rules and custom validators
- [Streaming](../essentials/usage#streaming-support) -- working with partial results
