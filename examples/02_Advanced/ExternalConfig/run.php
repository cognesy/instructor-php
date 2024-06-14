# External configuration file

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;

class User {
    public int $age;
    public string $name;
}

$config = [
];

// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withConfig($config);

// Call with custom model and execution mode
$user = $instructor->respond(
    messages: "Our user Jason is 25 years old.",
    responseModel: User::class,
    model: 'gpt-3.5-turbo',
    mode: Mode::Json,
);


dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
