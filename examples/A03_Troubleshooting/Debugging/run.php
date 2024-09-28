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

use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;
use Cognesy\Instructor\Extras\LLM\Drivers\OpenAIDriver;
use Cognesy\Instructor\Extras\LLM\Enums\LLMProviderType;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

class User {
    public int $age;
    public string $name;
}

// CASE 1 - normal flow

$instructor = (new Instructor)->withConnection('openai');

echo "\n### CASE 1.1 - Debugging sync request\n\n";
$user = $instructor->withDebug()->respond(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
    options: [ 'stream' => false ]
);

echo "\nResult:\n";
dump($user);

echo "\n### CASE 1.2 - Debugging streaming request\n\n";
$user2 = $instructor->withDebug()->respond(
    messages: "Anna is 21 years old.",
    responseModel: User::class,
    options: [ 'stream' => true ]
);

echo "\nResult:\n";
dump($user2);

assert(isset($user->name));
assert(isset($user->age));
assert($user->name === 'Jason');
assert($user->age === 25);

assert(isset($user2->name));
assert(isset($user2->age));
assert($user2->name === 'Anna');
assert($user2->age === 21);


// CASE 2 - forcing API error via empty LLM config

$driver = new OpenAIDriver(new LLMConfig());
$instructor = (new Instructor)->withDriver($driver);

echo "\n### CASE 2 - Debugging exception\n\n";
try {
    $user = $instructor->withDebug()->respond(
        messages: "Jason is 25 years old.",
        responseModel: User::class,
        options: [ 'stream' => true ]
    );
} catch (Exception $e) {
    echo "\nCaught it:\n";
    dump($e);
}
?>
```
