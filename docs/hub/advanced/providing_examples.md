# Providing example inputs and outputs

To improve the results of LLM inference you can provide examples of the expected output.
This will help LLM to understand the context and the expected structure of the output.

It is typically useful in the `Mode::Json` and `Mode::MdJson` modes, where the output
is expected to be a JSON object.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Data\Example;

class User {
    public int $age;
    public string $name;
}

echo "\nREQUEST:\n";
$user = (new Instructor)
    ->onEvent(RequestSentToLLM::class, fn($event)=>dump($event->request->body()))
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
                output: ['name' => 'Ian', 'age' => 27]
            ),
        ],
        mode: Mode::Json)
    ->get();

echo "\nOUTPUT:\n";
dump($user);
?>
```
