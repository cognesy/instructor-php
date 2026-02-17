---
title: 'Context caching (text inference)'
docname: 'context_cache_llm'
id: '7d51'
---
## Overview

Instructor offers a simplified way to work with LLM providers' APIs supporting caching
(currently only Anthropic API), so you can focus on your business logic while still being
able to take advantage of lower latency and costs.

> **Note 1:** Instructor supports context caching for Anthropic API and OpenAI API.

> **Note 2:** Context caching is automatic for all OpenAI API calls. Read more
> in the [OpenAI API documentation](https://platform.openai.com/docs/guides/prompt-caching).

## Example

When you need to process multiple requests with the same context, you can use context
caching to improve performance and reduce costs.

In our example we will be analyzing the README.md file of this Github project and
generating its summary for 2 target audiences.


```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

$cacheNonce = bin2hex(random_bytes(8));

// Note: Prompt caching has minimum token requirements that vary by model:
// - Claude Opus/Sonnet: 1,024 tokens minimum
// - Claude Haiku 3.5: 2,048 tokens minimum
// - Claude Haiku 4.5: 4,096 tokens minimum
// If cached content is below threshold, caching silently doesn't occur.
$model = 'claude-sonnet-4-20250514'; // Using Sonnet for lower cache threshold (1,024 tokens)

$data = file_get_contents(__DIR__ . '/../../../README.md');

$inference = (new Inference)
    //->wiretap(fn($e) => $e->print()) // wiretap to print all events
    //->withDebugPreset('on') // debug HTTP traffic
    ->using('anthropic')
    ->withCachedContext(
        messages: [
            ['role' => 'user', 'content' => 'Here is content of README.md file'],
            ['role' => 'user', 'content' => $data],
            ['role' => 'user', 'content' => 'Generate a short, very domain specific pitch of the project described in README.md. List relevant, domain specific problems that this project could solve. Use domain specific concepts and terminology to make the description resonate with the target audience.'],
            ['role' => 'assistant', 'content' => "For whom do you want to generate the pitch?\nCache nonce: {$cacheNonce}"],
        ],
    );

$response = $inference
    ->with(
        messages: [['role' => 'user', 'content' => 'founder of lead gen SaaS startup']],
        model: $model,
        options: ['max_tokens' => 512],
    )
    ->response();

print("----------------------------------------\n");
print("\n# Summary for CTO of lead gen vendor\n");
print("  ({$response->usage()->cacheReadTokens} tokens read from cache)\n\n");
print("----------------------------------------\n");
print($response->content() . "\n");

assert(!empty($response->content()));
assert(Str::contains($response->content(), 'Instructor'));
assert(Str::contains($response->content(), 'lead', false));
assert($response->usage()->cacheWriteTokens > 0);

$response2 = $inference
    ->with(
        messages: [['role' => 'user', 'content' => 'CIO of insurance company']],
        model: $model,
        options: ['max_tokens' => 512],
    )
    ->response();

print("----------------------------------------\n");
print("\n# Summary for CIO of insurance company\n");
print("  ({$response2->usage()->cacheReadTokens} tokens read from cache)\n\n");
print("----------------------------------------\n");
print($response2->content() . "\n");

assert(!empty($response2->content()));
assert(Str::contains($response2->content(), 'Instructor'));
assert(Str::contains($response2->content(), 'insurance', false));
assert($response2->usage()->cacheReadTokens > 0);
?>
```
