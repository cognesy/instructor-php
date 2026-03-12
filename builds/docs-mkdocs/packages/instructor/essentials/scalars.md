---
title: Scalars
description: 'Extract one value without creating a full class.'
---

Sometimes you need a single value -- a number, a string, a boolean -- without the
overhead of defining a dedicated response class. The `Scalar` adapter handles this
by wrapping a single typed field in a minimal schema.


## Basic Usage

```php
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\StructuredOutput;

$age = (new StructuredOutput)
    ->with(
        messages: 'Jason is 28 years old.',
        responseModel: Scalar::integer('age'),
    )
    ->get();

// int(28)
// @doctest id="dbb9"
```

The first argument to each factory method is the field name, which gives the model
semantic context about what value to extract. An optional second argument provides
a description for additional guidance.


## Available Types

### String

```php
$name = (new StructuredOutput)
    ->with(
        messages: 'Jason is 28 years old.',
        responseModel: Scalar::string(name: 'firstName'),
    )
    ->get();

// string("Jason")
// @doctest id="4089"
```

### Integer

```php
$age = (new StructuredOutput)
    ->with(
        messages: 'Jason is 28 years old.',
        responseModel: Scalar::integer('age'),
    )
    ->get();

// int(28)
// @doctest id="dced"
```

### Float

```php
$time = (new StructuredOutput)
    ->with(
        messages: 'His 100m sprint record is 11.6 seconds.',
        responseModel: Scalar::float(name: 'recordTime'),
    )
    ->get();

// float(11.6)
// @doctest id="cd54"
```

### Boolean

```php
$isAdult = (new StructuredOutput)
    ->with(
        messages: 'Jason is 28 years old.',
        responseModel: Scalar::boolean(name: 'isAdult'),
    )
    ->get();

// bool(true)
// @doctest id="a897"
```

### Enum

Use `Scalar::enum()` to select one value from a backed enum:

```php
enum CitizenshipGroup: string {
    case EU = 'eu';
    case US = 'us';
    case Other = 'other';
}

$group = (new StructuredOutput)
    ->with(
        messages: 'Jason is 28 years old and lives in Germany.',
        responseModel: Scalar::enum(CitizenshipGroup::class, name: 'citizenshipGroup'),
    )
    ->get();

// CitizenshipGroup::Other
// @doctest id="67af"
```

The model sees the enum's backed values as the allowed options and returns one of them.
Instructor deserializes it back into the enum instance.


## Factory Method Signatures

All factory methods accept the same optional parameters:

```php
Scalar::string(
    name: 'value',            // Field name shown to the model
    description: 'Response value', // Additional guidance
    required: true,           // Whether the field is required
    defaultValue: null,       // Default if not extracted
);
// @doctest id="e55d"
```

| Factory | PHP return type |
|---|---|
| `Scalar::string(...)` | `string` |
| `Scalar::integer(...)` | `int` |
| `Scalar::float(...)` | `float` |
| `Scalar::boolean(...)` | `bool` |
| `Scalar::enum(...)` | The backed enum instance |


## Typed Convenience Methods

When you are already working with a `StructuredOutput` or `PendingStructuredOutput`
instance, you can skip `get()` and call a typed accessor that validates the return type:

```php
$age = (new StructuredOutput)
    ->with(messages: 'Jason is 28.', responseModel: Scalar::integer('age'))
    ->getInt();

$name = (new StructuredOutput)
    ->with(messages: 'Jason is 28.', responseModel: Scalar::string('name'))
    ->getString();
// @doctest id="19c2"
```

Available typed methods: `getString()`, `getInt()`, `getFloat()`, `getBoolean()`.

These methods throw an exception if the result is not of the expected type, which
provides an extra safety check beyond what `get()` offers.
