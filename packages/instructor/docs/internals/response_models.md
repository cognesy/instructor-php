---
title: 'Response Models'
description: 'How schemas are derived from the target type.'
---

## Overview

The `responseModel` parameter tells Instructor the shape of the data you want back
from the LLM. Instructor translates it into a JSON Schema, uses that schema to
guide the model, and then deserializes the model's output back into the requested
shape.


## Supported Input Types

### Class String

The most common approach. Pass a fully qualified class name and Instructor analyzes
its properties, type hints, and doc comments to generate the schema:

```php
$user = (new StructuredOutput())
    ->with(
        messages: 'Jason is 25 years old',
        responseModel: User::class,
    )
    ->get();
```

Using `User::class` gives your IDE full visibility for refactoring, autocompletion,
and static analysis.

### Object Instance

Pass an object instance when you need to pre-populate default values on the
response model:

```php
$user = new User();
$user->country = 'US'; // default

$result = (new StructuredOutput())
    ->with(
        messages: 'Jason is 25 years old',
        responseModel: $user,
    )
    ->get();
```

Instructor inspects the class of the instance and generates the same schema it
would for the class string, but uses the instance's property values as defaults.

### JSON Schema Array

Pass a raw JSON Schema array when you need full control over the schema definition:

```php
$result = (new StructuredOutput())
    ->with(
        responseModel: [
            'x-php-class' => User::class,
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name', 'age'],
        ],
        messages: 'Jason is 25 years old',
    )
    ->get();
```

> **Important:** The `x-php-class` field is required so Instructor knows which class
> to deserialize the response into. Without it, a dynamic `Structure` object is
> used instead.

### Helper Wrappers

The package ships with convenience wrappers for common patterns:

- **`Scalar`** -- extract a single scalar value (string, int, float, bool, or enum)
- **`Sequence`** -- extract a list of objects, with per-item streaming support
- **`Maybe`** -- extract a value that may or may not be present

```php
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Extras\Sequence\Sequence;

// Single integer
$age = (new StructuredOutput())
    ->with(messages: 'Jason is 25', responseModel: Scalar::integer('age'))
    ->get();

// List of users
$users = (new StructuredOutput())
    ->with(messages: $text, responseModel: Sequence::of(User::class))
    ->get();
```


## Output Formats

By default, Instructor returns a typed PHP object. You can change the output format
using fluent methods on `StructuredOutput`:

```php
// Return an associative array instead of an object
$array = (new StructuredOutput())
    ->with(responseModel: User::class, messages: '...')
    ->intoArray()
    ->get();

// Deserialize into a different class than the schema source
$dto = (new StructuredOutput())
    ->with(responseModel: UserProfile::class, messages: '...')
    ->intoInstanceOf(UserDTO::class)
    ->get();

// Use a self-deserializing object
$result = (new StructuredOutput())
    ->with(responseModel: Rating::class, messages: '...')
    ->intoObject(Scalar::integer('rating'))
    ->get();
```

> **Note:** Instructor always returns objects (or arrays when `intoArray()` is used).
> It never returns raw arrays unless explicitly requested.


## Custom Response Handling

You can customize how Instructor processes the response model at each stage by
implementing one or more of the following contracts on your response model class:

| Contract | Phase | Purpose |
|---|---|---|
| `CanProvideJsonSchema` | Schema generation | Provide a raw JSON Schema array, bypassing class analysis |
| `CanProvideSchema` | Schema generation | Provide a `Schema` object, bypassing class analysis |
| `CanDeserializeSelf` | Deserialization | Custom deserialization from the extracted JSON data |
| `CanValidateSelf` | Validation | Replace the default validation process entirely |
| `CanTransformSelf` | Transformation | Transform the validated object before returning it to the caller |

These contracts are executed in order during the response processing pipeline.
When implementing custom handling, split logic across the relevant methods rather
than doing everything in a single block.

### Example Implementations

The built-in `Scalar` and `Sequence` helpers are practical examples of this
customization pattern. They implement custom schema providers, deserialization,
validation, and transformation to support scalar values and ordered lists
through wrapper classes:

- `packages/instructor/src/Extras/Scalar/`
- `packages/instructor/src/Extras/Sequence/`
