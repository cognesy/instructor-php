## FunctionCall helper class

Instructor offers FunctionCall class to extract arguments of a function
or method from content.

This is useful when you want to build tool use capability, e.g. for AI
chatbots or agents.



## Extracting arguments for a function

```php
<?php
use Cognesy\Addons\FunctionCall\FunctionCallFactory;
use Cognesy\Instructor\StructuredOutput;

/** Save user data to storage */
function saveUser(string $name, int $age, string $country) {
    // ...
}

$text = "His name is Jason, he is 28 years old and he lives in Germany.";
$args = (new StructuredOutput)->with(
    messages: $text,
    responseModel: FunctionCallFactory::fromFunctionName('saveUser'),
)->get();

// call the function with the extracted arguments
saveUser(...$args);
?>
```



## Extracting arguments for a method call

```php
<?php
use Cognesy\Addons\FunctionCall\FunctionCallFactory;
use Cognesy\Instructor\StructuredOutput;

class DataStore {
    /** Save user data to storage */
    public function saveUser(string $name, int $age, string $country) {
        // ...
    }
}

$text = "His name is Jason, he is 28 years old and he lives in Germany.";
$args = (new StructuredOutput)->with(
    messages: $text,
    responseModel: FunctionCallFactory::fromMethodName(Datastore::class, 'saveUser'),
)->get();

// call the function with the extracted arguments
(new DataStore)->saveUser(...$args);
?>
```



## Extracting arguments for a callable

```php
<?php
use Cognesy\Addons\FunctionCall\FunctionCallFactory;
use Cognesy\Instructor\StructuredOutput;

/** Save user data to storage */
$callable = function saveUser(string $name, int $age, string $country) {
    // ...
}

$text = "His name is Jason, he is 28 years old and he lives in Germany.";
$args = (new StructuredOutput)->with(
    messages: $text,
    responseModel: FunctionCallFactory::fromCallable($callable),
)->get();

// call the function with the extracted arguments
$callable(...$args);
?>
```
