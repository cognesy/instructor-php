---
title: 'Together.ai'
docname: 'togetherai'
---

## Overview

Together.ai hosts a number of language models and offers inference API with support for
chat completion, JSON completion, and tools call. You can use Instructor with Together.ai
as demonstrated below.

Please note that some Together.ai models support Mode::Tools or Mode::Json, which are much
more reliable than Mode::MdJson.

Mode compatibility:
- Mode::Tools - supported for selected models
- Mode::Json - supported for selected models
- Mode::MdJson - fallback mode


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;
use Cognesy\LLM\LLM\Enums\Mode;

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
$instructor = (new Instructor)->withConnection('together');

$user = $instructor
    ->respond(
        messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        examples: [[
            'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
            'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
        ],[
            'input' => 'We have a meeting with John, our new user. He is 30 years old - check his profile: @jx90.',
            'output' => ['name' => 'John', 'role' => 'admin', 'hobbies' => [], 'username' => 'jx90', 'age' => 30],
        ]],
        model: 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo',
        //options: ['stream' => true ]
        mode: Mode::Tools,
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
