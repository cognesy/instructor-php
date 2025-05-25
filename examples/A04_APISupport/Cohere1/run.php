---
title: 'Cohere'
docname: 'cohere'
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

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

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
// See: /config/llm.php to check or change LLM client connection configuration details
$structuredOutput = (new StructuredOutput)->using('cohere1');

$user = $structuredOutput->with(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
    model: 'command-r-plus',
    mode: OutputMode::MdJson,
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
