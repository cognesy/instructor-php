---
title: 'Providing example inputs and outputs'
docname: 'demonstrations'
---

## Overview

To improve the results of LLM inference you can provide examples of the expected output.
This will help LLM to understand the context and the expected structure of the output.

It is typically useful in the `Mode::Json` and `Mode::MdJson` modes, where the output
is expected to be a JSON object.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\HttpClient\RequestSentToLLM;
use Cognesy\Instructor\Instructor;

class User {
    public int $age;
    public string $name;
}

echo "\nREQUEST:\n";
$user = (new Instructor)
    ->onEvent(RequestSentToLLM::class, fn($event)=>dump($event->request->toMessages()))
    ->request(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        examples: [
            new Example(
                input: "John is 50 and works as a teacher.",
                output: ['name' => 'John', 'age' => 50]
            ),
            new Example(
                input: "We have recently hired Ian, who is 27 years old.",
                output: ['name' => 'Ian', 'age' => 27],
                template: "example input:\n<|input|>\noutput:\n```json\n<|output|>\n```\n",
            ),
        ],
        mode: Mode::Json)
    ->get();

echo "\nOUTPUT:\n";
dump($user);
assert($user->name === 'Jason');
assert($user->age === 25);
?>
```
