# Debugging

Instructor gives you access to SaloonPHP debugging mode by setting `options` array
key `debug` to `true` when creating a client instance.

Setting `debug` option to true causes underlying SaloonPHP library to output
HTTP request and response details to the console, so you can see what is being
sent to LLM API and what is being received.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;

class User {
    public int $age;
    public string $name;
}

$user = (new Instructor)->respond(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
    options: [
        'debug' => true
    ],
);

echo "\nResult:\n";
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
