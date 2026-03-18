---
title: 'Cohere'
docname: 'cohere'
id: 'd290'
tags:
  - 'api-support'
  - 'cohere'
  - 'provider'
---
## Overview

Instructor supports Cohere API - you can find the details on how to configure
the client in the example below.

Mode compatibility:
 - OutputMode::MdJson - supported, recommended as a fallback from JSON mode
 - OutputMode::Json - supported, recommended
 - OutputMode::Tools - partially supported, not recommended

Reasons OutputMode::Tools is not recommended:

 - Cohere does not support JSON Schema, which only allows to extract very simple, flat data schemas.
 - Performance of the currently available versions of Cohere models in tools mode for Instructor use case (data extraction) is extremely poor.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

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

// Get Instructor with specified LLM client connection
// Adjust provider credentials and model in the LLMConfig::fromArray([...]) values below.
$debugHttpClient = (new HttpClientBuilder)->withDebugConfig(DebugConfig::fromPreset('on'))->create();
$structuredOutput = new StructuredOutput(
    StructuredOutputRuntime::fromConfig(
        config: LLMConfig::fromPreset('cohere'),
        httpClient: $debugHttpClient,
    )->withOutputMode(OutputMode::Json)
);

$user = $structuredOutput->with(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
    model: 'command-r-plus-08-2024',
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
