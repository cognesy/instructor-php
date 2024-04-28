# Basic use with HandlesExtraction trait

Instructor provides `HandlesExtraction` trait that you can use to enable
extraction capabilities directly on class via static `extract()` method.

`extract()` method returns an instance of the class with the data extracted
using the Instructor.

`extract()` method has following signature (you can also find it in the
`CanHandleExtraction` interface):

```php
static public function extract(
    string|array $messages, // (required) The message(s) to extract data from
    string $model = '',     // (optional) The model to use for extraction (otherwise - use default)
    int $maxRetries = 2,    // (optional) The number of retries in case of validation failure
    array $options = [],    // (optional) Additional data to pass to the Instructor or LLM API
    Instructor $instructor = null // (optional) The Instructor instance to use for extraction
) : static;
```

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Extras\Mixin\HandlesExtraction;

class User {
    use HandlesExtraction;

    public int $age;
    public string $name;
}

$user = User::extract("Jason is 25 years old and works as an engineer.");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
