---
title: 'Debugging'
docname: 'debugging'
---

## Overview

The `Instructor` class has a `withDebug()` method that can be used to debug the request and response.

It displays detailed information about the request being sent to LLM API and response received from it,
including:
 - request headers, URI, method and body,
 - response status, headers, and body.

This is useful for debugging the request and response when you are not getting the expected results.

## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

class User {
    public int $age;
    public string $name;
}

$instructor = (new Instructor)->withClient(new OpenAIClient(
    apiKey: Env::get('OPENAI_API_KEY'),// . 'invalid', // intentionally invalid API key
    baseUri: Env::get('OPENAI_BASE_URI'),
));

echo "Debugging request and response:\n\n";
$user = $instructor->withDebug()->respond(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
    options: [ 'stream' => true ]
);

echo "\nResult:\n";
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
