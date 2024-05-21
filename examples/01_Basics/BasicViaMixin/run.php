# Basic use with HandlesSelfExtraction trait

Instructor provides `HandlesSelfExtraction` trait that you can use to enable
extraction capabilities directly on class via static `extract()` method.

`extract()` method returns an instance of the class with the data extracted
using the Instructor.

`extract()` method has following signature (you can also find it in the
`CanSelfExtract` interface):

```php
static public function extract(
    string|array $messages, // (required) The message(s) to extract data from
    string $model = '',     // (optional) The model to use for extraction (otherwise - use default)
    int $maxRetries = 2,    // (optional) The number of retries in case of validation failure
    array $options = [],    // (optional) Additional data to pass to the Instructor or LLM API
    array $examples = [],   // (optional) Examples to include in the prompt
    string $toolName = '',  // (optional) The name of the tool call - used to add semantic information for LLM
    string $toolDescription = '', // (optional) The description of the tool call - as above
    string $prompt = '',    // (optional) The prompt to use for extraction
    string $retryPrompt = '', // (optional) The prompt to use in case of validation failure
    Mode $mode = Mode::Tools, // (optional) The mode to use for extraction
    Instructor $instructor = null // (optional) The Instructor instance to use for extraction
) : static;
```

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Extras\Mixin\HandlesSelfExtraction;

class User {
    use HandlesSelfExtraction;

    public int $age;
    public string $name;
}

$user = User::extract("Jason is 25 years old and works as an engineer.");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
