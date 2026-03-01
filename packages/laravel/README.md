# Laravel Package

Laravel integration for InstructorPHP.

It provides:
- Laravel service provider and config
- Facades for `StructuredOutput`, `Inference`, `Embeddings`, and `AgentCtrl`
- testing fakes for facade-based tests
- Artisan commands for install, smoke-test, and response-model scaffolding

## Example

```php
<?php

use App\ResponseModels\PersonData;
use Cognesy\Instructor\Laravel\Facades\StructuredOutput;

$person = StructuredOutput::with(
    messages: 'John Smith is 30 years old',
    responseModel: PersonData::class,
)->get();
```

## Documentation

- `packages/laravel/docs/index.md`
- `packages/laravel/CHEATSHEET.md`
