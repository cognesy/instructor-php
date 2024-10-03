---
title: 'Tracking token usage via events'
docname: 'token_usage_events'
---

## Overview

Some use cases require tracking the token usage of the API responses.
Currently, this can be done by listening to the `ApiResponseReceived`
and `PartialApiResponseReceived` events and summing the token usage
of the responses.

Code below demonstrates how it can be implemented using Instructor
event listeners.

> Note: OpenAI API requires `stream_options` to be set to
> `['include_usage' => true]` to include token usage in the streamed
> responses.

## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Events\ApiClient\ApiResponseReceived;
use Cognesy\Instructor\Events\ApiClient\PartialApiResponseReceived;
use Cognesy\Instructor\Extras\LLM\Data\LLMApiResponse;
use Cognesy\Instructor\Extras\LLM\Data\PartialLLMApiResponse;
use Cognesy\Instructor\Instructor;

class User {
    public int $age;
    public string $name;
}

class TokenCounter {
    public int $input = 0;
    public int $output = 0;
    public int $cacheCreation = 0;
    public int $cacheRead = 0;

    public function add(LLMApiResponse|PartialLLMApiResponse $response) {
        $this->input += $response->inputTokens;
        $this->output += $response->outputTokens;
        $this->cacheCreation += $response->cacheCreationTokens;
        $this->cacheRead += $response->cacheReadTokens;
    }

    public function reset() {
        $this->input = 0;
        $this->output = 0;
        $this->cacheCreation = 0;
        $this->cacheRead = 0;
    }

    public function print() {
        echo "Input tokens: $this->input\n";
        echo "Output tokens: $this->output\n";
        echo "Cache creation tokens: $this->cacheCreation\n";
        echo "Cache read tokens: $this->cacheRead\n";
    }
}

$counter = new TokenCounter();

echo "COUNTING TOKENS FOR SYNC RESPONSE\n";
$text = "Jason is 25 years old and works as an engineer.";
$instructor = (new Instructor)
    ->onEvent(ApiResponseReceived::class, fn($e) => $counter->add($e->apiResponse))
    ->respond(
        messages: $text,
        responseModel: User::class,
);
echo "\nTEXT: $text\n";
assert($counter->input > 0);
assert($counter->output > 0);
$counter->print();

// Reset the counter
$counter->reset();

echo "\n\nCOUNTING TOKENS FOR STREAMED RESPONSE\n";
$text = "Anna is 19 years old.";
$instructor = (new Instructor)
    ->onEvent(PartialApiResponseReceived::class, fn($e) => $counter->add($e->partialApiResponse))
    ->respond(
        messages: $text,
        responseModel: User::class,
        options: ['stream' => true, 'stream_options' => ['include_usage' => true]],
);
echo "\nTEXT: $text\n";
assert($counter->input > 0);
assert($counter->output > 0);
$counter->print();
?>
```
