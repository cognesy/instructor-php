---
title: 'Receive all internal events with wiretap()'
docname: 'wiretap'
---

## Overview

# Receive all internal events with wiretap()

Instructor allows you to receive detailed information at every stage of request
and response processing via events.

`(new Instructor)->wiretap(callable $callback)` method allows you to receive all
events dispatched by Instructor.

Example below demonstrates how `wiretap()` can help you to monitor the execution
process and better understand or resolve any processing issues.

In this example we use `print()` method available on event classes, which outputs
console-formatted information about each event.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;

enum Role : string {
    case CEO = 'ceo';
    case CTO = 'cto';
    case Developer = 'developer';
    case Other = 'other';
}

class UserDetail
{
    public string $name;
    public Role $role;
    public int $age;
}

$user = (new Instructor)
    ->wiretap(fn($event) => $event->print())
    ->request(
        messages: [["role" => "user",  "content" => "Contact our CTO, Jason is 28 years old -- Best regards, Tom"]],
        responseModel: UserDetail::class,
        options: ['stream' => true]
    )
    ->get();

dump($user);

assert($user->name === "Jason");
assert($user->role === Role::CTO);
assert($user->age === 28);
?>
```
