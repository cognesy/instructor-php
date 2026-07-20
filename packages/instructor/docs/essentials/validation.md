---
title: Validation
description: 'Validate extracted data before you use it.'
---

Validation has two ordered layers. Instructor first validates extracted wire data against
the supported schema subset, before deserialization. For object targets, it then runs
Symfony or self-validation after deserialization and before transformation. If either
layer fails, Instructor can retry the request and feed safe validation errors back to the
model so it can self-correct.


## Local Schema Validation

Local schema validation applies to every final target, including arrays, `stdClass`, PHP
classes, self-deserializing objects, and explicit `Structure` values. It enforces:

- required properties and nullable values;
- string, integer, number, boolean, array, and object wire types;
- nested object/array-shape properties and collection item schemas, with dotted paths;
- string/integer enum options and backed PHP enum values;
- date/time values represented by strings or `DateTimeInterface` instances.

This is intentionally a supported subset, not a complete JSON Schema interpreter.
Keywords such as `pattern`, numeric ranges, string-length constraints, schema combinators,
conditionals, and `additionalProperties` are not enforced by the local schema-data
validator. A provider's native JSON Schema mode may enforce more. For PHP class targets,
use Symfony constraints or custom validation when those rules must also be checked
locally.


## Symfony Validation Attributes

Instructor uses the Symfony Validator component under the hood. Add constraint attributes
to your response model to enforce field-level rules:

```php
use Symfony\Component\Validator\Constraints as Assert;

class Person {
    #[Assert\NotBlank]
    #[Assert\Length(min: 3)]
    public string $name;

    #[Assert\PositiveOrZero]
    public int $age;
}
```

If the model returns a name shorter than three characters or a negative age, validation
fails and Instructor can retry the request automatically.

> For a full list of available constraints, see the
> [Symfony Validation documentation](https://symfony.com/doc/current/validation.html#constraints).


## Retries

Retries are configured on the runtime, not on individual requests. When validation fails
and retries are available, Instructor sends the validation errors back to the model and
asks it to try again:

```php
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$runtime = StructuredOutputRuntime::fromConfig(
    LLMConfig::fromPreset('openai')
)->withMaxRetries(3);
```

The `maxRetries` value controls how many additional attempts are allowed after the first
one. With `maxRetries(3)`, Instructor will try up to 4 times total (1 initial + 3 retries).

If all attempts fail validation, Instructor throws an exception.

```php
use Cognesy\Instructor\StructuredOutput;
use Symfony\Component\Validator\Constraints as Assert;

class Person {
    #[Assert\Length(min: 3)]
    public string $name;

    #[Assert\PositiveOrZero]
    public int $age;
}

$person = (new StructuredOutput)
    ->withRuntime($runtime)
    ->with(
        messages: 'His name is JX, aka Jason, he is -28 years old.',
        responseModel: Person::class,
    )
    ->get();
```

In this example, the model might initially return `name: "JX"` and `age: -28`. Validation
catches both issues, and the retry prompt tells the model what went wrong so it can
return `name: "Jason"` and `age: 28` on the next attempt.


## Custom Validation With `ValidationMixin`

For object-level validation logic that goes beyond simple field constraints, use the
`ValidationMixin` trait. Implement a `validate()` method that returns a `ValidationResult`:

```php
use Cognesy\Instructor\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Instructor\Validation\ValidationError;

class UserDetails
{
    use ValidationMixin;

    public string $name;
    public int $age;

    public function validate(): ValidationResult
    {
        if ($this->name !== strtoupper($this->name)) {
            return ValidationResult::fieldError(
                field: 'name',
                value: $this->name,
                message: 'Name must be in uppercase.',
            );
        }

        return ValidationResult::valid();
    }
}
```

The `ValidationResult` class provides several factory methods:

| Method | Purpose |
|---|---|
| `ValidationResult::valid()` | Indicates the object passed validation |
| `ValidationResult::invalid($errors)` | Wraps one or more `ValidationError` instances |
| `ValidationResult::fieldError($field, $value, $message)` | Shorthand for a single field error |
| `ValidationResult::make($errors, $message)` | General-purpose constructor |
| `ValidationResult::merge($results)` | Combines multiple validation results |

When validation fails, Instructor feeds the error messages back to the LLM on retry,
just like with Symfony constraints:

```php
$user = (new StructuredOutput)
    ->withRuntime($runtime)
    ->with(
        messages: 'jason is 25 years old',
        responseModel: UserDetails::class,
    )
    ->get();

assert($user->name === 'JASON');
```


## Custom Validation With Symfony `#[Assert\Callback]`

You can also use Symfony's `#[Assert\Callback]` attribute directly for full access to
the Symfony validation context. This is useful when you want to leverage Symfony's
violation builder API:

```php
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UserDetails
{
    public string $name;
    public int $age;

    #[Assert\Callback]
    public function validateName(ExecutionContextInterface $context, mixed $payload): void
    {
        if ($this->name !== strtoupper($this->name)) {
            $context->buildViolation('Name must be in uppercase.')
                ->atPath('name')
                ->setInvalidValue($this->name)
                ->addViolation();
        }
    }
}
```

> See the [Symfony Callback constraint docs](https://symfony.com/doc/current/reference/constraints/Callback.html)
> for more details on the violation builder API.


## How Retries Work

When a response fails validation, Instructor:

1. Collects all validation errors (from Symfony constraints, `ValidationMixin`, or both).
2. Formats them into a retry prompt that describes what went wrong.
3. Appends the retry prompt to the conversation history.
4. Sends the updated conversation back to the model for another attempt.

This self-correction loop continues until validation passes or the retry limit is reached.
The bundled retry prompt lists the validation failures. Customize it through a prompt
class in `StructuredOutputConfig`:

```php
use Cognesy\Instructor\Config\StructuredOutputConfig;

$config = new StructuredOutputConfig(
    maxRetries: 3,
    retryPromptClass: App\Prompts\RetryFeedbackPrompt::class,
);
```
