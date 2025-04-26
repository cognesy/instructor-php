## FunctionCall helper class

Instructor offers FunctionCall class to extract arguments of a function
or method from content.

This is useful when you want to build tool use capability, e.g. for AI
chatbots or agents.



## Extracting arguments for a function

```php
<?php
use Cognesy\Addons\FunctionCall\FunctionCall;
use Cognesy\Instructor\Instructor;

/** Save user data to storage */
function saveUser(string $name, int $age, string $country) {
    // ...
}

$text = "His name is Jason, he is 28 years old and he lives in Germany.";
$args = (new Instructor)->respond(
    messages: $text,
    responseModel: FunctionCall::fromFunctionName('saveUser'),
);

// call the function with the extracted arguments
saveUser(...$args);
?>
```



## Extracting arguments for a method call

```php
<?php
use Cognesy\Addons\FunctionCall\FunctionCall;
use Cognesy\Instructor\Instructor;

class DataStore {
    /** Save user data to storage */
    public function saveUser(string $name, int $age, string $country) {
        // ...
    }
}

$text = "His name is Jason, he is 28 years old and he lives in Germany.";
$args = (new Instructor)->respond(
    messages: $text,
    responseModel: FunctionCall::fromMethodName(Datastore::class, 'saveUser'),
);

// call the function with the extracted arguments
(new DataStore)->saveUser(...$args);
?>
```



## Extracting arguments for a callable

```php
<?php
use Cognesy\Addons\FunctionCall\FunctionCall;
use Cognesy\Instructor\Instructor;

/** Save user data to storage */
$callable = function saveUser(string $name, int $age, string $country) {
    // ...
}

$text = "His name is Jason, he is 28 years old and he lives in Germany.";
$args = (new Instructor)->respond(
    messages: $text,
    responseModel: FunctionCall::fromCallable($callable),
);

// call the function with the extracted arguments
$callable(...$args);
?>
```
