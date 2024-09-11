---
title: 'OpenAI'
docname: 'openai'
---

## Overview

This is the default client used by Instructor.

Mode compatibility:
 - Mode::Tools (supported)
 - Mode::Json (supported)
 - Mode::JsonSchema (recommended for new models)
 - Mode::MdJson (fallback)

## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

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

// OpenAI auth params
$yourApiKey = Env::get('OPENAI_API_KEY'); // use your own API key

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient('openai');

$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    model: 'gpt-4o-mini', // set your own value/source
    mode: Mode::JsonSchema,
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. His Twitter handle is @frankch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => '@frankch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
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
