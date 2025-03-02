---
title: 'Receive specific internal event with onEvent()'
docname: 'on_event'
---

## Overview

`(new Instructor)->onEvent(string $class, callable $callback)` method allows
you to receive callback when specified type of event is dispatched by Instructor.

This way you can plug into the execution process and monitor it, for example logging
or reacting to the events which are of interest to your application.

This example demonstrates how you can monitor outgoing requests and received responses
via Instructor's events.

Check the `Cognesy\Instructor\Events` namespace for the list of available events
and their properties.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Instructor;
use Cognesy\Polyglot\Http\Events\HttpRequestSent;
use Cognesy\Polyglot\Http\Events\HttpResponseReceived;
use Cognesy\Utils\Events\Event;

class User
{
    public string $name;
    public int $age;
}

// let's mock a logger class - in real life you would use a proper logger, e.g. Monolog
$logger = new class {
    public function log(Event $event) {
        // we're using a predefined asLog() method to get the event data,
        // but you can access the event properties directly and customize the output
        echo $event->asLog()."\n";
    }
};

$user = (new Instructor)
    ->onEvent(HttpRequestSent::class, fn($event) => $logger->log($event))
    ->onEvent(HttpResponseReceived::class, fn($event) => $logger->log($event))
    ->request(
        messages: "Jason is 28 years old",
        responseModel: User::class,
    )
    ->get();

dump($user);
?>
```
