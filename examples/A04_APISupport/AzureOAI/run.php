---
title: 'Azure OpenAI'
docname: 'azure_openai'
id: 'f972'
tags:
  - 'api-support'
  - 'azure-openai'
  - 'provider'
---
## Overview

You can connect to Azure OpenAI instance using a dedicated client provided
by Instructor. Please note it requires setting up your own model deployment
using Azure OpenAI service console.


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
    StructuredOutputRuntime::fromProvider(LLMProvider::using('azure'))
        ->withOutputMode(OutputMode::Json)
);

// Call with your model name and preferred execution mode
$user = $structuredOutput->with(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
    model: 'gpt-4o-mini', // set your own value/source
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
