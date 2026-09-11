---
title: 'Context caching (text inference)'
docname: 'context_cache_llm'
id: '7d51'
tags:
  - 'llm-advanced'
  - 'context-caching'
  - 'text-inference'
---
## Overview

Instructor offers a simplified way to work with LLM providers' APIs supporting caching
(currently only Anthropic API), so you can focus on your business logic while still being
able to take advantage of lower latency and costs.

> **Note 1:** Instructor supports context caching for Anthropic API and OpenAI API.

> **Note 2:** Anthropic automatic caching is opt-in through the top-level
> `cache_control` option; `withCachedContext()` instead sets explicit breakpoints.
> See the [Anthropic API documentation](https://platform.claude.com/docs/en/build-with-claude/prompt-caching).
> Claude Haiku 4.5 requires a 4,096-token prefix; Sonnet 4.5/4.6 require 1,024 tokens
> (verified September 6, 2026). Shorter prefixes silently bypass caching.

## Example

When you need to process multiple requests with the same context, you can use context
caching to improve performance and reduce costs.

In our example we will be analyzing the README.md file of this Github project and
generating its summary for 2 target audiences.


```php
<?php
require 'examples/boot.php';

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$data = file_get_contents(__DIR__.'/../../../README.md');

$inference = Inference::using('anthropic')
    ->withCachedContext(
        messages: Messages::fromArray([
            ['role' => 'user', 'content' => 'Here is content of README.md file'],
            ['role' => 'user', 'content' => $data],
            ['role' => 'user', 'content' => 'Generate a short, very domain specific pitch of the project described in README.md. List relevant, domain specific problems that this project could solve. Use domain specific concepts and terminology to make the description resonate with the target audience.'],
            ['role' => 'assistant', 'content' => 'For whom do you want to generate the pitch?'],
        ]),
    );

$response = $inference
    ->with(
        messages: Messages::fromString('founder of lead gen SaaS startup'),
        options: ['max_tokens' => 512],
    )
    ->response();

echo "----------------------------------------\n";
echo "\n# Summary for CTO of lead gen vendor\n";
echo "  ({$response->usage()->cacheWriteTokens} tokens written to cache, {$response->usage()->cacheReadTokens} tokens read from cache)\n\n";
echo "----------------------------------------\n";
echo $response->message()->content()->toString()."\n";

assert(! empty($response->message()->content()->toString()));
assert(
    $response->usage()->cacheWriteTokens + $response->usage()->cacheReadTokens > 0,
    'Expected Anthropic to write or reuse the cached README prefix',
);

$response2 = $inference
    ->with(
        messages: Messages::fromString('CIO of insurance company'),
        options: ['max_tokens' => 512],
    )
    ->response();

echo "----------------------------------------\n";
echo "\n# Summary for CIO of insurance company\n";
echo "  ({$response2->usage()->cacheWriteTokens} tokens written to cache, {$response2->usage()->cacheReadTokens} tokens read from cache)\n\n";
echo "----------------------------------------\n";
echo $response2->message()->content()->toString()."\n";

assert(! empty($response2->message()->content()->toString()));
assert($response2->usage()->cacheReadTokens > 0, 'Expected the second request to reuse the cached prefix');
?>
```
