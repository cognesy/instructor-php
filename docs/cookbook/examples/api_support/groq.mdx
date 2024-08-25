---
title: 'Groq'
docname: 'groq'
---

## Overview

Groq is LLM providers offering a very fast inference thanks to their
custom hardware. They provide a several models - Llama2, Mixtral and Gemma.

Supported modes depend on the specific model, but generally include:
 - Mode::MdJson - fallback mode
 - Mode::Json - recommended
 - Mode::Tools - supported

Here's how you can use Instructor with Groq API.

## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\Groq\GroqClient;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

enum UserType : string {
    case Guest = 'guest';
    case User = 'user';
    case Admin = 'admin';
}

class User {
    public string $name;
    public UserType $role;
    /** @var string[] */
    public array $hobbies;
    public string $username;
    public ?int $age;
}

// Mistral instance params
$yourApiKey = Env::get('GROQ_API_KEY'); // set your own API key

// Create instance of client initialized with custom parameters
$client = new GroqClient(
    apiKey: $yourApiKey,
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor
    ->respond(
        messages: "Jason (@jxnlco) is 25 years old. He is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        prompt: 'Parse the user data to JSON, respond using following JSON Schema: <|json_schema|>',
        examples: [[
            'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
            'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'user', 'hobbies' => ['playing drums'],],
        ],[
            'input' => 'We have a meeting with John, our new admin who likes surfing. He is 19 years old - check his profile: @jx90.',
            'output' => ['name' => 'John', 'role' => 'admin', 'hobbies' => ['surfing'], 'username' => 'jx90', 'age' => 19],
        ]],
        model: 'gemma2-9b-it',
        maxRetries: 2,
        options: ['temperature' => 0.5],
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
