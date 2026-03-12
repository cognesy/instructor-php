---
title: Plain Text Responses
description: Plain text is the default inference path -- no configuration required.
---

Text is the simplest and most portable output format. When you do not set `responseFormat`, Polyglot asks the provider for a normal text response. Every provider supports this shape, making it the safest default for any use case.

## Basic Usage

A minimal text request requires nothing more than a message:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withMessages(Messages::fromString('What is the single responsibility principle?'))
    ->get();
```

The `get()` method returns the raw string content from the model's response. There is no JSON parsing, no schema validation -- just the text the model produced.

## When to Use Text Mode

Plain text is ideal for:

- Simple question answering
- Creative content generation (stories, poems, copy)
- Conversational interactions and chat
- Summaries and paraphrasing
- Any use case where structured data is not required

## Working Across Providers

Text mode works consistently across all providers, making it the most portable option. You can swap providers without changing anything else about your request:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

// Using OpenAI
$response = Inference::using('openai')
    ->withMessages(Messages::fromString('Write a short poem about the ocean.'))
    ->get();

// Using Anthropic -- same API, same result shape
$response = Inference::using('anthropic')
    ->withMessages(Messages::fromString('Write a short poem about the ocean.'))
    ->get();
```

## Using the `with()` Method

You can also use the `with()` method to pass messages alongside other parameters. Since text is the default, there is no need to specify a response format:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->with(
        messages: Messages::fromString('Explain the SOLID principles in one paragraph.'),
        options: ['temperature' => 0.3],
    )
    ->get();
```

## Streaming Text Responses

For long-form content, you may want to stream the response so your application can display output as it arrives:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages(Messages::fromString('Write a short essay about renewable energy.'))
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}
```

Each delta contains a `contentDelta` with the next chunk of text from the model. Streaming works with all providers that support it.

## Accessing the Full Response

If you need metadata beyond the raw text -- such as token usage or the finish reason -- use the `response()` method instead of `get()`:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->withMessages(Messages::fromString('What is photosynthesis?'))
    ->response();

$text = $response->content();
$usage = $response->usage();
$reason = $response->finishReason();
```
