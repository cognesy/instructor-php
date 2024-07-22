---
title: 'Local / Ollama'
docname: 'ollama'
---

## Overview

You can use Instructor with local Ollama instance. Please note that, at least currently,
OS models do not perform on par with OpenAI (GPT-3.5 or GPT-4) model.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\Ollama\OllamaClient;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Events\Request\ResponseReceivedFromLLM;
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

// Create instance of Ollama client with default settings
$client = new OllamaClient();

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

// Listen to events to print request/response data
$instructor->onEvent(RequestSentToLLM::class, function($event) {
    print("Request sent to LLM:\n\n");

    dump($event->request);
});
$instructor->onEvent(ResponseReceivedFromLLM::class, function($event) {
    print("Received response from LLM:\n\n");

    dump($event->response);
});

$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    prompt: 'Parse the user data to JSON, respond using following JSON Schema: <|json_schema|>',
    examples: [[
        'input' => 'Ive got email Frank - their developer. Asked to connect via Twitter @frankch. Btw, he plays on drums!',
        'output' => ['name' => 'Frank', 'role' => 'developer', 'hobbies' => ['playing drums'], 'username' => 'frankch', 'age' => null],
    ],[
        'input' => 'We have a meeting with John, our new user. He is 30 years old - check his profile: @jx90.',
        'output' => ['name' => 'John', 'role' => 'admin', 'hobbies' => [], 'username' => 'jx90', 'age' => 30],
    ]],
    model: 'qwen2',
    mode: Mode::Json,
);


print("Completed response model:\n\n");

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
