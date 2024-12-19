---
title: 'Anthropic'
docname: 'anthropic'
---

## Overview

Instructor supports Anthropic API - you can find the details on how to configure
the client in the example below.

Mode compatibility:
- Mode::MdJson, Mode::Json - supported
- Mode::Tools - not supported yet


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;

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
$instructor = (new Instructor)->withConnection('anthropic');

$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
    model: 'claude-3-haiku-20240307',
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
