---
title: 'Custom prompts'
docname: 'custom_prompts'
id: '2b77'
---
## Overview

In case you want to take control over the prompts sent by Instructor
to LLM for different modes, you can use the `prompt` parameter in the
`request()` or `create()` methods.

It will override the default Instructor prompts, allowing you to fully
customize how LLM is instructed to process the input.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class User {
    public int $age;
    public string $name;
}

$structuredOutput = (new StructuredOutput)
    // let's dump the request data to see how customized prompts look like in requests
    ->onEvent(HttpRequestSent::class, fn(HttpRequestSent $event) => dump($event));

print("\n# Request for OutputMode::Tools:\n\n");
$user = $structuredOutput
    ->with(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to extract correct and accurate data from the messages using provided tools.\n",
        mode: OutputMode::Tools
    )->get();
echo "\nRESPONSE:\n";
dump($user);

print("\n# Request for OutputMode::Json:\n\n");
$user = $structuredOutput
    ->with(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to respond correctly with JSON object. Response must follow JSONSchema:\n<|json_schema|>\n",
        mode: OutputMode::Json
    )->get();
echo "\nRESPONSE:\n";
dump($user);

print("\n# Request for OutputMode::MdJson:\n\n");
$user = $structuredOutput
    ->with(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to respond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n<|json_schema|>\n",
        mode: OutputMode::MdJson
    )->get();
echo "\nRESPONSE:\n";
dump($user);

?>
```
