---
title: 'Custom prompts'
docname: 'custom_prompts'
---

## Overview

In case you want to take control over the prompts sent by Instructor
to LLM for different modes, you can use the `prompt` parameter in the
`request()` or `respond()` methods.

It will override the default Instructor prompts, allowing you to fully
customize how LLM is instructed to process the input.

## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\HttpClient\RequestSentToLLM;
use Cognesy\Instructor\Instructor;

class User {
    public int $age;
    public string $name;
}

$instructor = (new Instructor)
    // let's dump the request data to see how customized prompts look like in requests
    ->onEvent(RequestSentToLLM::class, fn(RequestSentToLLM $event) => dump($event));

print("\n# Request for Mode::Tools:\n\n");
$user = $instructor
    ->respond(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to extract correct and accurate data from the messages using provided tools.\n",
        mode: Mode::Tools
    );
echo "\nRESPONSE:\n";
dump($user);

print("\n# Request for Mode::Json:\n\n");
$user = $instructor
    ->respond(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to respond correctly with JSON object. Response must follow JSONSchema:\n<|json_schema|>\n",
        mode: Mode::Json
    );
echo "\nRESPONSE:\n";
dump($user);

print("\n# Request for Mode::MdJson:\n\n");
$user = $instructor
    ->respond(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to respond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n<|json_schema|>\n",
        mode: Mode::MdJson
    );
echo "\nRESPONSE:\n";
dump($user);

?>
```
