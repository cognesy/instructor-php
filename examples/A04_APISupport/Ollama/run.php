---
title: 'Local / Ollama'
docname: 'ollama'
---

## Overview

You can use Instructor with local Ollama instance.

Please note that, at least currently, OS models do not perform on par with OpenAI (GPT-3.5 or GPT-4) model for complex data schemas.

Supported modes:
 - Mode::MdJson - fallback mode, works with any capable model
 - Mode::Json - recommended
 - Mode::Tools - supported (for selected models - check Ollama docs)

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
$instructor = (new Instructor)->withConnection('ollama');

$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    examples: [[
        'input' => 'Ive got email Frank - their developer. Asked to connect via Twitter @frankch. Btw, he plays on drums!',
        'output' => ['name' => 'Frank', 'role' => 'developer', 'hobbies' => ['playing drums'], 'username' => 'frankch', 'age' => null],
    ],[
        'input' => 'We have a meeting with John, our new user. He is 30 years old - check his profile: @j90.',
        'output' => ['name' => 'John', 'role' => 'admin', 'hobbies' => [], 'username' => 'j90', 'age' => 30],
    ]],
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
