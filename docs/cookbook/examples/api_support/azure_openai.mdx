---
title: 'Azure OpenAI'
docname: 'azure_openai'
---

## Overview

You can connect to Azure OpenAI instance using a dedicated client provided
by Instructor. Please note it requires setting up your own model deployment
using Azure OpenAI service console.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\Azure\AzureClient;
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

/// Custom client parameters: base URI
$client = (new AzureClient(
    apiKey: Env::get('AZURE_OPENAI_API_KEY'), // set your own value/source
    resourceName: Env::get('AZURE_OPENAI_RESOURCE_NAME'), // set your own value/source
    deploymentId: Env::get('AZURE_OPENAI_DEPLOYMENT_NAME'), // set your own value/source
    apiVersion: Env::get('AZURE_OPENAI_API_VERSION'), // set your own value/source
));

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

// Call with your model name and preferred execution mode
$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
    model: 'gpt-4o-mini', // set your own value/source
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
