---
title: 'Basic use via mixin'
docname: 'basic_use_mixin'
---

## Overview

Instructor provides `HandlesSelfInference` trait that you can use to enable
extraction capabilities directly on class via static `infer()` method.

`infer()` method returns an instance of the class with the data extracted
using the Instructor.

`infer()` method has following signature (you can also find it in the
`CanSelfInfer` interface):

```php
static public function infer(
    string|array $messages, // (required) The message(s) to infer data from
    string $prompt = '',    // (optional) The prompt to use for inference
    array $examples = [],   // (optional) Examples to include in the prompt
    string $model = '',     // (optional) The model to use for inference (otherwise - use default)
    int $maxRetries = 2,    // (optional) The number of retries in case of validation failure
    array $options = [],    // (optional) Additional data to pass to the Instructor or LLM API
    Mode $mode = OutputMode::Tools, // (optional) The mode to use for inference
    ?LLM $llm = null         // (optional) LLM instance to use for inference
) : static;
```

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Mixin\HandlesSelfInference;

class User {
    use HandlesSelfInference;

    public int $age;
    public string $name;
}

$user = User::infer("Jason is 25 years old and works as an engineer.");

dump($user);

assert(isset($user->name));
assert(isset($user->age));
assert($user->name === 'Jason');
assert($user->age === 25);
?>
```
