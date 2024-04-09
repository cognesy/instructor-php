# Receive and process Instructor's internal events

Instructor allows you to receive detailed information at every stage of request
and response processing via events.

* `(new Instructor)->onEvent(string $class, callable $callback)` method - receive
callback when specified type of event is dispatched
* `(new Instructor)->wiretap(callable $callback)` method - receive any event
dispatched by Instructor, may be useful for debugging or performance analysis
* `(new Instructor)->onError(callable $callback)` method - receive callback on
any uncaught error, so you can customize handling it, for example logging the
error or using some fallback mechanism in an attempt to recover

Receiving events can help you to monitor the execution process and makes it easier
for a developer to understand and resolve any processing issues.

This is an example of wiretapping to receive all events dispatched by Instructor
during the processing of a request.

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
    ->request(
        messages: [["role" => "user",  "content" => "Contact our CTO, Jason is 28 years old -- Best regards, Tom"]],
        responseModel: UserDetail::class,
        options: ['stream' => true]
    )
    ->wiretap(fn($event) => $event->print())
    ->last();

dump($user);

assert($user->name === "Jason");
assert($user->role === Role::CTO);
assert($user->age === 28);
?>
```
