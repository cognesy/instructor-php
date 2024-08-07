---
title: 'OpenRouter'
docname: 'openrouter'
---

## Overview

You can use Instructor with OpenRouter API. OpenRouter provides easy, unified access
to multiple open source and commercial models. Read OpenRouter docs to learn more about
the models they support.

Please note that OS models are in general weaker than OpenAI ones, which may result in
lower quality of responses or extraction errors. You can mitigate this (partially) by using
validation and `maxRetries` option to make Instructor automatically reattempt the extraction
in case of extraction issues.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\OpenRouter\OpenRouterClient;
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

// OpenRouter client params
$yourApiKey = Env::get('OPENROUTER_API_KEY'); // or your own value/source

// Create instance of OpenAI client initialized with custom parameters
$client = new OpenRouterClient(
    apiKey: $yourApiKey,
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ],[
        'input' => 'We have a meeting with John, our new user. He is 30 years old - check his profile: @jx90.',
        'output' => ['name' => 'John', 'role' => 'admin', 'hobbies' => [], 'username' => 'jx90', 'age' => 30],
    ]],
    model: 'google/gemma-2-9b-it:free',
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
