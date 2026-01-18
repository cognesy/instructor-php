<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

$data = file_get_contents(__DIR__ . '/../../../README.md');
$cacheNonce = bin2hex(random_bytes(8));

// Note: Prompt caching has minimum token requirements that vary by model:
// - Claude Opus/Sonnet: 1,024 tokens minimum
// - Claude Haiku 3.5: 2,048 tokens minimum
// - Claude Haiku 4.5: 4,096 tokens minimum
// If cached content is below threshold, caching silently doesn't occur.
$model = 'claude-sonnet-4-20250514'; // Using Sonnet for lower cache threshold (1,024 tokens)

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
