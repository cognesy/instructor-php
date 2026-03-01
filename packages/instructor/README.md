# Instructor Package

Core structured-output engine for InstructorPHP.

Use it to turn unstructured LLM responses into typed PHP data, with validation, retries, and streaming updates.

## Example

```php
<?php

use Cognesy\Instructor\StructuredOutput;

class Person {
    public string $name;
    public int $age;
}

$person = StructuredOutput::using('openai')
    ->with(
        messages: 'His name is Jason and he is 28 years old.',
        responseModel: Person::class,
    )
    ->get();
```

## Documentation

- `packages/instructor/docs/quickstart.md`
- `packages/instructor/docs/essentials/usage.md`
- `packages/instructor/docs/_meta.yaml`
