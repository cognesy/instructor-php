---
title: 'Extracting arguments of function or method'
docname: 'function_calls'
---

## Overview

Instructor offers FunctionCall class to extract arguments of a function
or method from content.

This is useful when you want to build tool use capability, e.g. for AI
chatbots or agents.


## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Addons\FunctionCall\FunctionCallFactory;
use Cognesy\Instructor\StructuredOutput;

class DataStore
{
    /** Save user data to storage */
    public function saveUser(string $name, int $age, string $country) : void {
        // Save user to database
        echo "Saving user ... saveUser('$name', $age, '$country')\n";
    }
}

$text = "His name is Jason, he is 28 years old and he lives in Germany.";
$args = (new StructuredOutput)->with(
    messages: $text,
    responseModel: FunctionCallFactory::fromMethodName(DataStore::class, 'saveUser'),
)->get();

echo "\nCalling the function with the extracted arguments:\n";
(new DataStore)->saveUser(...$args);

echo "\nExtracted arguments:\n";
dump($args);

assert(count($args) == 3);
expect($args['name'] === 'Jason');
expect($args['age'] == 28);
expect($args['country'] === 'Germany');
?>
```
