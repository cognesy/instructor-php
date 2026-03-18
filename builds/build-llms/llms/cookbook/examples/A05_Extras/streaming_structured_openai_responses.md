---
title: 'Streaming (Structured Output, OpenAI Responses)'
docname: 'streaming_structured_openai_responses'
id: '1f8e'
tags:
  - 'extras'
  - 'streaming'
  - 'openai-responses'
---
## Overview

A minimal structured-output streaming example using the `openai-responses`
connection config. The example verifies that streaming yields partial updates and that
we receive the expected final fields.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Str;

class PersonProfile
{
    public string $name = '';
    public int $age = 0;
    /** @var string[] */
    public array $hobbies = [];
}

function partialScreenState(?bool $active = null): bool {
    static $state = false;

    if (!is_null($active)) {
        $state = $active;
    }

    return $state;
}

function enterPartialScreen(): void {
    if (partialScreenState()) {
        return;
    }

    partialScreenState(true);
    register_shutdown_function(static function (): void {
        exitPartialScreen();
    });

    echo "\033[?1049h\033[H\033[2J";

    if (defined('STDOUT')) {
        fflush(STDOUT);
    }
}

function exitPartialScreen(): void {
    if (!partialScreenState()) {
        return;
    }

    echo "\033[?1049l";

    if (defined('STDOUT')) {
        fflush(STDOUT);
    }

    partialScreenState(false);
}

$text = <<<TEXT
Jason is 25 years old. He lives in San Francisco. He enjoys soccer, climbing,
and cooking.
TEXT;

$partialsCount = 0;
$onPartialUpdate = function (object $partial) use (&$partialsCount): void {
    $partialsCount += 1;
    enterPartialScreen();
    echo "\033[H\033[2J";

    echo "Partial update #{$partialsCount}:\n";
    dump($partial);

    if (defined('STDOUT')) {
        fflush(STDOUT);
    }
};

$runtime = StructuredOutputRuntime::fromProvider(
    LLMProvider::using('openai-responses'),
)->withOutputMode(OutputMode::JsonSchema);

$stream = (new StructuredOutput($runtime))
    // ->withHttpClient(...) // pass a debug-enabled HTTP client when needed
    ->withResponseClass(PersonProfile::class)
    ->withMessages($text)
    ->withOptions(['max_output_tokens' => 384])
    ->withStreaming()
    ->stream();

foreach ($stream->partials() as $partial) {
    $onPartialUpdate($partial);
}

$profile = $stream->finalValue();
exitPartialScreen();

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
