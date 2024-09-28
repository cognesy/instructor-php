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
$instructor = (new Instructor)->withConnection('openrouter');

$user = $instructor->withDebug()->respond(
        messages: "Jason (@jxnlco) is 25 years old. He is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        prompt: 'Parse the user data to JSON, respond using following JSON Schema: <|json_schema|>',
        examples: [[
            'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
            'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'user', 'hobbies' => ['playing drums'],],
        ],[
            'input' => 'We have a meeting with John, our new admin who likes surfing. He is 19 years old - check his profile: @jig.',
            'output' => ['age' => 19, 'name' => 'John', 'username' => 'jig', 'role' => 'admin', 'hobbies' => ['surfing'],],
        ]],
    model: 'microsoft/phi-3.5-mini-128k-instruct',
    //options: ['stream' => true ]
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
