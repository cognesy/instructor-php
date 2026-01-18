<?php
require 'examples/boot.php';

use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class User {
    public int $age;
    public string $name;
}

echo "\nREQUEST:\n";
$user = (new StructuredOutput)
    // let's dump the request data to see how examples are used in requests
    ->onEvent(HttpRequestSent::class, fn($event) => dump($event))
    ->withMessages("Our user Jason is 25 years old.")
    ->withResponseClass(User::class)
    ->withExamples([
        new Example(
            input: "John is 50 and works as a teacher.",
            output: ['name' => 'John', 'age' => 50]
        ),
        new Example(
            input: "We have recently hired Ian, who is 27 years old.",
            output: ['name' => 'Ian', 'age' => 27],
            template: "example input:\n<|input|>\noutput:\n```json\n<|output|>\n```\n",
        ),
    ])
    ->withOutputMode(OutputMode::Json)
    ->get();

echo "\nOUTPUT:\n";
dump($user);
assert($user->name === 'Jason');
assert($user->age === 25);
?>
