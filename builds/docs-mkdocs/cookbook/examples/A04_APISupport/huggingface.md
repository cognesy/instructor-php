---
title: 'Hugging Face'
docname: 'huggingface'
id: 'da2a'
tags:
  - 'api-support'
  - 'huggingface'
  - 'provider'
---
## Overview

You can use Instructor to parse structured output from LLMs using Hugging Face API.
This example demonstrates how to parse user data into a structured model using
JSON Schema.

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
    public string $firstName;
    public UserType $role;
    /** @var string[] */
    public array $hobbies;
    public string $username;
    public ?int $age;
}

$structuredOutput = new StructuredOutput(
    StructuredOutputRuntime::fromProvider(LLMProvider::using('huggingface'))
        ->withOutputMode(OutputMode::Json)
        ->withMaxRetries(2)
);

$user = $structuredOutput
    ->with(
        messages: "Jason (@jxnlco) is 25 years old. He is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        prompt: 'Parse the user data to JSON, respond using following JSON Schema: <|json_schema|>',
        examples: [[
                      'input' => 'I\'ve got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
                      'output' => ['firstName' => 'Frank', 'age' => 30, 'username' => 'frank@hk.ch', 'role' => 'user', 'hobbies' => ['playing drums'],],
                  ],[
                      'input' => 'We have a meeting with John, our new admin who likes surfing. He is 19 years old - check his profile: @jx90.',
                      'output' => ['firstName' => 'John', 'role' => 'admin', 'hobbies' => ['surfing'], 'username' => 'jx90', 'age' => 19],
                  ]],
        //model: 'deepseek-ai/DeepSeek-R1-0528-Qwen3-8B',
        options: ['temperature' => 0.5],
    )->get();

print("Completed response model:\n\n");

dump($user);

assert(isset($user->firstName));
assert(isset($user->role));
assert(isset($user->age));
assert(isset($user->hobbies));
assert(isset($user->username));
assert(is_array($user->hobbies));
assert(count($user->hobbies) > 0);
assert($user->role === UserType::Admin);
assert($user->age === 25);
assert($user->firstName === 'Jason');
assert(in_array($user->username, ['jxnlco', '@jxnlco']));
?>
```
