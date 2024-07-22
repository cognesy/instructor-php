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

use Cognesy\Instructor\Instructor;

class User {
    public int $age;
    public string $name;
}

echo "Debugging request and response:\n\n";
$user = (new Instructor)->withDebug()->respond(
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
