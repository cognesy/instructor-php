---
title: 'Qwen'
docname: 'qwen'
id: '6a2e'
tags:
  - 'api-support'
  - 'qwen'
  - 'provider'
---
## Overview

QwenCloud provides an OpenAI-compatible API with tool calling, JSON output,
and native JSON Schema support on its current Qwen3.8-Max model.

Mode compatibility:
- OutputMode::Tools (supported)
- OutputMode::Json (supported)
- OutputMode::JsonSchema (supported by Qwen3.8-Max)
- OutputMode::MdJson (fallback)

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

enum UserType : string {
    case Guest = 'guest';
    case User = 'user';
    case Admin = 'admin';
}

class User {
    public int $age;
    public string $name;
    public string $username;
    public UserType $role;
    /** @var string[] */
    public array $hobbies;
}

$structuredOutput = new StructuredOutput(
    StructuredOutputRuntime::fromProvider(LLMProvider::using('qwen'))
        ->withOutputMode(OutputMode::JsonSchema)
);

$user = $structuredOutput->with(
    messages: 'Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.',
    responseModel: User::class,
    model: 'qwen3.8-max',
    examples: [[
        'input' => 'Frank is a 30-year-old developer with the Twitter handle @frankch. He plays drums.',
        'output' => [
            'age' => 30,
            'name' => 'Frank',
            'username' => '@frankch',
            'role' => 'user',
            'hobbies' => ['playing drums'],
        ],
    ]],
)->get();

print("Completed response model:\n\n");

dump($user);

assert($user->name === 'Jason');
assert($user->age === 25);
assert($user->role === UserType::Admin);
assert(is_array($user->hobbies));
assert(count($user->hobbies) > 0);
assert(in_array($user->username, ['jxnlco', '@jxnlco']));
?>
```
