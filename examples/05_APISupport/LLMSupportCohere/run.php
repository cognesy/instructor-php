---
title: 'Cohere'
docname: 'cohere'
---

## Overview

Instructor supports Cohere API - you can find the details on how to configure
the client in the example below.

Mode compatibility:
 - Mode::MdJson - supported, recommended
 - Mode::Json - not supported by Cohere
 - Mode::Tools - partially supported, not recommended

Reasons Mode::Tools is not recommended:

 - Cohere does not support JSON Schema, which only allows to extract very simple data schemas.
 - Performance of the currently available versions of Cohere models in tools mode for Instructor use case (data extraction) is extremely poor.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\Cohere\CohereClient;
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

// Create instance of client initialized with custom parameters
$client = new CohereClient(
    apiKey: Env::get('COHERE_API_KEY'),
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    model: 'command-r-plus',
    mode: Mode::Json,
    examples: [[
        'input' => 'Ive got email Frank - their developer. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
        'output' => ['age' => null, 'name' => 'Frank', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
);

print("Completed response model:\n\n");

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
