# Custom prompts

In case you want to take control over the prompts sent by Instructor
to LLM for different modes, you can use the `prompt` parameter in the
`request()` or `respond()` methods.

It will override the default Instructor prompts, allowing you to fully
customize how LLM is instructed to process the input.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Instructor;

class User {
    public int $age;
    public string $name;
}

$instructor = (new Instructor)
    ->onEvent(RequestSentToLLM::class, fn($event)=>dump($event->request->body()));

print("\n# Request for Mode::Tools:\n\n");
$user = $instructor
    ->respond(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to extract correct and accurate data from the messages using provided tools.\n",
        toolName: 'extract',
        toolDescription: 'Extract information from provided content',
        mode: Mode::Tools);

print("\n# Request for Mode::Json:\n\n");
$user = $instructor
    ->respond(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to respond correctly with JSON object. Response must follow JSONSchema:\n<|json_schema|>\n",
        mode: Mode::Json);

print("\n# Request for Mode::MdJson:\n\n");
$user = $instructor
    ->respond(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to respond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n<|json_schema|>\n",
        mode: Mode::MdJson);

?>
```
