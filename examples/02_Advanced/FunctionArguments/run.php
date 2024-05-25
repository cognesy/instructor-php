# Extracting arguments of function or method

Instructor offers Call class to extract arguments of a function or method from content.
This is useful when you want to build tool usage capability, e.g. for AI chatbots or agents.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Extras\Call\Call;
use Cognesy\Instructor\Instructor;

/** Save user data to storage */
function saveUser(string $name, int $age, string $country) {
    // Save user to database
    echo "Saving user ... saveUser('$name', $age, '$country')\n";
}

$text = "His name is Jason, he is 28 years old and he lives in Germany.";
$args = (new Instructor)->respond(
    messages: $text,
    responseModel: Call::fromCallable(saveUser(...)),
);

echo "\nCalling the function with the extracted arguments:\n";
saveUser(...$args);

echo "\nExtracted arguments:\n";
dump($args);

assert(count($args) == 3);
expect($args['name'] == 'Jason');
expect($args['age'] == 28);
expect($args['country'] == 'Germany');
?>
```
