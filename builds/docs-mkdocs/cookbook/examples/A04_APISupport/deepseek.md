---
title: 'DeepSeek'
docname: 'deepseek'
id: '2302'
tags:
  - 'api-support'
  - 'deepseek'
  - 'provider'
---
## Overview

Support for DeepSeek API which provides strong models at affordable price.

Mode compatibility:
- OutputMode::Tools (supported)
- OutputMode::Json (supported)
- OutputMode::JsonSchema (supported)
- OutputMode::MdJson (fallback)

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Enums\OutputMode;
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
    StructuredOutputRuntime::fromProvider(LLMProvider::using('deepseek'))
        ->withOutputMode(OutputMode::JsonSchema)
);

$user = $structuredOutput->with(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    model: 'deepseek-chat', // set your own value/source
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. His Twitter handle is @frankch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => '@frankch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
)->get();

print("Completed response model:\n\n");

dump($user);

assert(isset($user->name));
assert(isset($user->role));
assert(isset($user->age));
assert(isset($user->hobbies));
assert(isset($user->username));
assert(is_array($user->hobbies));
assert(count($user->hobbies) > 0);
assert($user->role === UserType::Admin);
assert($user->age === 25);
assert($user->name === 'Jason');
assert(in_array($user->username, ['jxnlco', '@jxnlco']));
?>
```
