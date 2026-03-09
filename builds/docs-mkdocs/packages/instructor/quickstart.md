---
title: Quickstart
description: 'Start extracting typed data in a few minutes.'
---

## Install

```bash
composer require cognesy/instructor-struct
# @doctest id="a855"
```

## Extract A Typed Object

Set your provider credentials in the environment, then make a request:

```php
use Cognesy\Instructor\StructuredOutput;

final class City {
    public string $name;
    public string $country;
}

$city = StructuredOutput::using('openai')
    ->with(
        messages: 'What is the capital of France?',
        responseModel: City::class,
    )
    ->get();
// @doctest id="1f55"
```

`$city` is a `City` instance. Public typed properties define the shape Instructor asks the model to return.

## Keep It Simple

- Use `StructuredOutput::using('<preset>')` when a preset is enough
- Use `StructuredOutput::fromConfig(...)` when you want an explicit `LLMConfig`
- Use `StructuredOutputRuntime` when you need retries, events, or custom pipeline behavior

## Next

- [Setup](setup)
- [Usage](essentials/usage)
- [Data Model](essentials/data_model)
