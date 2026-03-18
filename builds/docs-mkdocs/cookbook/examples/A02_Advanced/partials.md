---
title: 'Streaming partial updates during inference'
docname: 'partials'
id: 'c2dc'
tags:
  - 'advanced'
  - 'streaming'
  - 'partial-updates'
---
## Overview

Instructor can process LLM's streamed responses to provide partial updates that you
can use to update the model with new data as the response is being generated. You can
use it to improve user experience by updating the UI with partial data before the full
response is received.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\LLMProvider;
class UserRole
{
    /** Monotonically increasing identifier */
    public int $id;
    public string $title = '';
}

class UserDetail
{
    public int $age;
    public string $name;
    public string $location;
    /** @var UserRole[] */
    public array $roles;
    /** @var string[] */
    public array $hobbies;
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

// This function will be called every time a new token is received
function partialUpdate($partial) {
    enterPartialScreen();
    echo "\033[H\033[2J";

    echo "Updated partial object received:\n";
    dump($partial);

    if (defined('STDOUT')) {
        fflush(STDOUT);
    }
}
?>
```
Now we can use this data model to extract arbitrary properties from a text message.
As the tokens are streamed from LLM API, the `partialUpdate` function will be called
with partially updated object of type `UserDetail` that you can use, usually to update
the UI.

```php
<?php
$text = <<<TEXT
    Jason is 25 years old, he is an engineer and tech lead. He lives in
    San Francisco. He likes to play soccer and climb mountains.
    TEXT;

$system = 'You are a precise structured data extraction assistant for JSON output. '
    . 'Copy values exactly from the source text. '
    . 'Do not omit explicitly stated person names.';

$prompt = 'Extract one user profile from the text as JSON. '
    . 'Always fill name, age, location, roles, and hobbies when the source text provides them. '
    . 'Use the exact person name from the text.';

$stream = (new StructuredOutput(
    StructuredOutputRuntime::fromProvider(LLMProvider::using('openai'))
        ->withConfig(new StructuredOutputConfig(
            responseCachePolicy: ResponseCachePolicy::None, // use Memory if you need replay
        ))
        ->withOutputMode(OutputMode::Json)
))->with(
        messages: $text,
        responseModel: UserDetail::class,
        system: $system,
        prompt: $prompt,
    )
    ->withStreaming()
    ->stream();

$partials = [];
foreach ($stream->partials() as $partial) {
    // Streams are one-shot by default. Keep updates if you need to inspect them later.
    $partials[] = $partial;
    partialUpdate($partial);
}

$user = $stream->finalValue();
exitPartialScreen();

echo "All tokens received, fully completed object available in `\$user` variable.\n";
echo '$user = '."\n";
dump($user);

assert(!empty($user->roles));
assert(!empty($user->hobbies));
assert($user->location === 'San Francisco');
assert($user->age == 25);
assert($user->name === 'Jason');
?>
```
