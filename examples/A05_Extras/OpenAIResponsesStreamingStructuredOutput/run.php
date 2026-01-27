---
title: 'Streaming (Structured Output, OpenAI Responses)'
docname: 'streaming_structured_openai_responses'
---

## Overview

A minimal structured-output streaming example using the `openai-responses`
preset. The example verifies that streaming yields partial updates and that
we receive the expected final fields.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Cli\Console;
use Cognesy\Utils\Str;

class PersonProfile
{
    public string $name = '';
    public int $age = 0;
    /** @var string[] */
    public array $hobbies = [];
}

$text = <<<TEXT
Jason is 25 years old. He lives in San Francisco. He enjoys soccer, climbing,
and cooking.
TEXT;

$partialsCount = 0;
$onPartialUpdate = function (object $partial) use (&$partialsCount): void {
    $partialsCount += 1;
    Console::clearScreen();
    echo "Partial update #{$partialsCount}:\n";
    dump($partial);
};

$profile = (new StructuredOutput)
    ->using('openai-responses')
    ->withOutputMode(OutputMode::JsonSchema)
    ->withResponseClass(PersonProfile::class)
    ->withMessages($text)
    ->withOptions(['max_output_tokens' => 384])
    ->withStreaming()
    ->onPartialUpdate($onPartialUpdate)
    ->get();

echo "All tokens received. Final structured profile:\n";
dump($profile);

assert($partialsCount > 0, 'Expected at least one partial update');
assert(Str::contains($profile->name, 'Jason'), 'Expected name Jason');
assert($profile->age === 25, 'Expected age 25');

$hobbiesLower = array_map(static fn(string $hobby): string => strtolower($hobby), $profile->hobbies);
assert(in_array('soccer', $hobbiesLower, true), 'Expected hobby soccer');
assert(in_array('climbing', $hobbiesLower, true), 'Expected hobby climbing');
?>
```
