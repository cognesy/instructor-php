---
title: 'SambaNova'
docname: 'sambanova'
---

## Overview

Support for SambaNova's API, which provide fast inference endpoints for Llama and Qwen LLMs.

Mode compatibility:
- Mode::MdJson (supported)
- Mode::Tools (not supported)
- Mode::Json (not supported)
- Mode::JsonSchema (not supported)

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Instructor;
use Cognesy\Polyglot\LLM\Enums\Mode;
use Cognesy\Utils\Debug\Debug;

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
$instructor = (new Instructor)->withConnection('sambanova');
Debug::enable();
$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. His Twitter handle is @frankch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => '@frankch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
    model: 'Meta-Llama-3.1-8B-Instruct', // set your own value/source
    mode: Mode::Json,
);

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
