---
title: 'Function Calls'
description: 'Extract function and method arguments from natural language using LLMs.'
---

Instructor uses tool-calling internally when the runtime operates in `OutputMode::Tools`, which is the default mode. For most applications, this is an implementation detail -- you define a response model and read the result.

However, the `FunctionCall` addon takes this concept further by letting you extract arguments for real PHP functions, methods, or closures directly from natural language. This is particularly useful when building tool-use capabilities for AI chatbots or agents.

## Extracting Arguments for a Function

The `FunctionCallFactory` inspects a function's signature via reflection and builds a response model that matches its parameters. The LLM then extracts the correct argument values from the input text.

```php
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

// Call the function with extracted arguments
saveUser(...$args);
// @doctest id="a177"
```

The docblock comment on the function is included in the schema sent to the LLM, giving it additional context about what the function does.

## Extracting Arguments for a Method

You can also extract arguments for class methods by specifying both the class and method name.

```php
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
    responseModel: FunctionCallFactory::fromMethodName(DataStore::class, 'saveUser'),
)->get();

(new DataStore)->saveUser(...$args);
// @doctest id="d081"
```

## Extracting Arguments for a Callable

Closures and other callables work the same way.

```php
use Cognesy\Addons\FunctionCall\FunctionCallFactory;
use Cognesy\Instructor\StructuredOutput;

/** Save user data to storage */
$callable = function(string $name, int $age, string $country) {
    // ...
};

$text = "His name is Jason, he is 28 years old and he lives in Germany.";

$args = (new StructuredOutput)->with(
    messages: $text,
    responseModel: FunctionCallFactory::fromCallable($callable),
)->get();

$callable(...$args);
// @doctest id="9d86"
```

## How It Works

Under the hood, `FunctionCallFactory` uses `CallableSchemaFactory` to reflect on the callable's parameters and produce a `Schema` object. That schema is then wrapped in a `Structure` that Instructor can use as a response model. The LLM receives a JSON Schema derived from the function signature (including parameter names, types, and docblock descriptions) and returns matching values.

## Output Modes

By default, Instructor uses `OutputMode::Tools` (tool-calling). You only need to change the mode when a specific provider or workflow requires JSON output instead of tool-calling. The function call extraction works with any output mode.
