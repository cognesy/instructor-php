# Instructor Package

Core structured-output engine for InstructorPHP.

Use it to turn unstructured LLM responses into typed PHP data, with validation, retries, and streaming updates.

## Example

```php
<?php

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

class Person {
    public string $name;
    public int $age;
}

$person = StructuredOutput::fromConfig(LLMConfig::fromArray(['driver' => 'openai']))
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
