---
title: Context Caching
description: Attach stable context to later requests for reduced token usage.
---

When you are asking multiple questions against the same background material -- a system prompt,
a long document, a set of tools -- resending that material with every request wastes tokens and
increases latency. Polyglot's `withCachedContext()` method lets you separate the **stable
parts** of a conversation from the **per-call messages**, so the provider can cache and reuse
them across requests.


## How Context Caching Works

Without caching, every request includes the full conversation history, system prompt, and any
reference material. As conversations grow, this can lead to significant token overhead.

Context caching solves this by marking a portion of the request as a reusable prefix. The
provider stores this prefix server-side and references it on subsequent requests, reducing both
the number of tokens processed and the time to first token.

```
Request 1:  [cached context] + [new question]  -->  cache write
Request 2:  [cached context] + [new question]  -->  cache read (faster, cheaper)
Request 3:  [cached context] + [new question]  -->  cache read (faster, cheaper)
```


## Using Cached Context

The `withCachedContext()` method accepts the same kinds of data you would normally pass through
`with()`, but treats them as a persistent prefix for subsequent requests:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$inference = Inference::using('anthropic')->withCachedContext(
    messages: [
        ['role' => 'system', 'content' => 'You are a helpful assistant who provides concise answers.'],
        ['role' => 'user', 'content' => 'I want to discuss machine learning concepts.'],
        ['role' => 'assistant', 'content' => 'I would be happy to discuss machine learning. What aspect interests you?'],
    ],
);

$response1 = $inference
    ->withMessages('What is supervised learning?')
    ->response();

echo $response1->content() . "\n";
echo 'Cache read tokens: ' . $response1->usage()->cacheReadTokens . "\n";

$response2 = $inference
    ->withMessages('And what about unsupervised learning?')
    ->response();

echo $response2->content() . "\n";
echo 'Cache read tokens: ' . $response2->usage()->cacheReadTokens . "\n";
```

The first request populates the provider's cache (you may see `cacheWriteTokens` reported).
Every subsequent request that shares the same prefix benefits from a cache hit, reflected in
`cacheReadTokens`.


## What Can Be Cached

The cached context can include any combination of:

- **messages** -- system prompts, conversation history, or reference material
- **tools** -- tool/function definitions that remain constant across calls
- **toolChoice** -- the tool selection strategy
- **responseFormat** -- a fixed response schema

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$inference = Inference::using('anthropic')->withCachedContext(
    messages: [
        ['role' => 'system', 'content' => 'You are a data extraction assistant.'],
    ],
    tools: [
        [
            'type' => 'function',
            'function' => [
                'name' => 'extract_entities',
                'description' => 'Extract named entities from text.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'entities' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'required' => ['entities'],
                ],
            ],
        ],
    ],
    toolChoice: 'auto',
);

// Each follow-up query reuses the cached system prompt and tool definitions
$response = $inference->withMessages('Extract entities from: "Apple announced the new iPhone in Cupertino."')->response();
```


## Processing Large Documents

Context caching is particularly valuable when working with large documents. Instead of
resending the full document with every question, you cache it once and issue lightweight
follow-up queries:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

$document = file_get_contents('large_document.txt');

$inference = Inference::using('anthropic')->withCachedContext(
    messages: [
        ['role' => 'system', 'content' => 'You will help analyze and summarize documents.'],
        ['role' => 'user', 'content' => "Here is the document to analyze:\n\n" . $document],
    ],
);

$questions = [
    'Summarize the key points in 3 bullets.',
    'What are the main arguments presented?',
    'Are there any contradictions in the text?',
    'What conclusions can be drawn?',
];

foreach ($questions as $question) {
    $response = $inference->withMessages($question)->response();

    echo "Q: {$question}\n";
    echo "A: " . $response->content() . "\n";
    echo "Cache read tokens: " . $response->usage()->cacheReadTokens . "\n\n";
}
```

After the first request populates the provider's cache, subsequent questions benefit from
reduced input token processing, which lowers both cost and latency.


## Inspecting Cache Usage

If a provider reports cache usage, you can inspect it through `response()->usage()`. The
`Usage` object exposes the following cache-related fields when available:

| Field | Description |
|---|---|
| `cacheReadTokens` | Tokens served from the cache (cache hit) |
| `cacheWriteTokens` | Tokens written to the cache (cache miss / first request) |

```php
<?php

$response = $inference->withMessages('Summarize the document.')->response();

$usage = $response->usage();

echo "Input tokens:       " . $usage->inputTokens . "\n";
echo "Output tokens:      " . $usage->outputTokens . "\n";
echo "Cache read tokens:  " . $usage->cacheReadTokens . "\n";
echo "Cache write tokens: " . $usage->cacheWriteTokens . "\n";
```


## Provider Support

Different providers handle context caching differently:

| Provider | Caching Behavior | Cache Metrics |
|---|---|---|
| **Anthropic** | Explicit cache markers with native support | Full reporting (`cacheReadTokens`, `cacheWriteTokens`) |
| **OpenAI** | Automatic server-side prompt caching | Limited reporting; no opt-in required |
| **Other providers** | No native caching | Polyglot manages conversation state correctly; no cache metrics |

Polyglot sends the appropriate cache control markers to providers that support them. For
providers without native caching support, `withCachedContext()` still works correctly --
the context is prepended to each request -- but you will not see cache-related usage metrics
in the response.

> **Tip:** To maximize cache hit rates with Anthropic, keep your cached context stable across
> requests. Even small changes to the cached portion will invalidate the cache and trigger a
> new cache write.
