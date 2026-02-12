---
title: 'Context caching (text inference, OpenAI)'
docname: 'context_cache_llm_oai'
id: '63a7'
---
## Overview

Instructor offers a simplified way to work with LLM providers' APIs supporting caching,
so you can focus on your business logic while still being able to take advantage of lower
latency and costs.

> **Note:** Context caching is automatic for all OpenAI API calls. Read more
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

$data = file_get_contents(__DIR__ . '/../../../README.md');

$inference = (new Inference)
    //->wiretap(fn($e) => $e->print()) // wiretap to print all events
    //->withDebugPreset('on') // debug HTTP traffic
    ->using('openai')
    ->withCachedContext(
        messages: [
            ['role' => 'user', 'content' => 'Here is content of README.md file'],
            ['role' => 'user', 'content' => $data],
            ['role' => 'user', 'content' => 'Generate a short, very domain specific pitch of the project described in README.md. List relevant, domain specific problems that this project could solve. Use domain specific concepts and terminology to make the description resonate with the target audience.'],
            ['role' => 'assistant', 'content' => 'For whom do you want to generate the pitch?'],
        ],
    );

$response = $inference
    ->with(
        messages: [['role' => 'user', 'content' => 'founder of lead gen SaaS startup']],
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
if ($response->usage()->cacheReadTokens === 0 && $response->usage()->cacheWriteTokens === 0) {
    print("Note: cacheReadTokens/cacheWriteTokens are 0. Prompt caching applies only to eligible models and prompt sizes.\n");
}

$response2 = $inference
    ->with(
        messages: [['role' => 'user', 'content' => 'CIO of insurance company']],
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
if ($response2->usage()->cacheReadTokens === 0) {
    print("Note: cacheReadTokens is 0. Prompt caching applies only to eligible models and prompt sizes.\n");
}
?>
```
