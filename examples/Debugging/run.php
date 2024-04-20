# Debugging

Instructor gives you access to SaloonPHP debugging mode via Instructor class or
API client class `withDebug()` methods.

Calling `withDebug()` causes underlying SaloonPHP library to output HTTP request
and response details to the console, so you can see what is being sent to LLM API
and what is being received.

You can also directly access Saloon connector instance via `connector()` method
on the client instance, and call Saloon debugging methods on it - see SaloonPHP
debugging documentation for more details:
https://docs.saloon.dev/the-basics/debugging

Additionally, `connector()` method on the client instance allows you to access
other capabilities of Saloon connector, such as setting or modifying middleware.
See SaloonPHP documentation for more details:
https://docs.saloon.dev/digging-deeper/middleware

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;

class User {
    public int $age;
    public string $name;
}

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withDebug();

$user = $instructor->respond(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
);

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
