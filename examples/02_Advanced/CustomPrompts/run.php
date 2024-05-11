# Custom prompts

In case you want to take control over the prompts sent by Instructor
to LLM for different modes, you can use the `prompt` parameter in the
`request()` or `respond()` methods.

It will override the default Instructor prompts, allowing you to fully
customize how LLM is instructed to process the input.

Note that various models and API providers have specific requirements
on the input format, e.g. for OpenAI JSON mode you are required to include
`JSON` string in the prompt.

Instructor takes care of automatically setting the `response_format`
parameter, but this may not be sufficient for some models - they require
specifying JSON response format as part of the prompt, rather than just
as `response_format` parameter in the request (e.g. OpenAI).

For this reason, when using Instructor's `Mode::Json` and `Mode::MdJson`
consider including the expected JSON Schema in the prompt. Otherwise, the
response is unlikely to match your target model, making it impossible for
Instructor to deserialize it correctly.

`Mode::Tools` makes use of `$toolName` and `$toolDescription`
parameters to provide additional context to the LLM, describing the tool
to be used for processing the input. `Mode::Json` and `Mode::MdJson` ignore
these parameters, as tools are not used in these modes.

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

$jsonSchema = json_encode($instructor->createJsonSchema(User::class));

print("\n# Request for Mode::Tools:\n\n");
$user = $instructor
    ->request(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to extract correct and accurate data from the messages using provided tools.\n",
        toolName: 'extract',
        toolDescription: 'Extract information from provided content',
        mode: Mode::Tools)
    ->get();

print("\n# Request for Mode::Json:\n\n");
$user = $instructor
    ->request(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to respond correctly with JSON object. Response must follow JSONSchema:\n" . $jsonSchema,
        mode: Mode::Json)
    ->get();

print("\n# Request for Mode::MdJson:\n\n");
$user = $instructor
    ->request(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to respond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n" . $jsonSchema,
        mode: Mode::MdJson)
    ->get();

?>
```
