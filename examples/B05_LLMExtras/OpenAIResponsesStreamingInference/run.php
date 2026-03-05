---
title: 'Streaming (Inference, OpenAI Responses)'
docname: 'streaming_inference_openai_responses'
id: '983e'
---
## Overview

A minimal streaming example using explicit typed config:
`Inference::using('openai-responses')`.
The example verifies that streaming produces deltas and that the final
response contains the expected marker.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

$expectedPhrase = 'paris';
$prompt = 'Describe the history of Paris in exactly 3 sentences.';

$stream = Inference::using('openai-responses')
    ->withMessages($prompt)
    ->withOptions(['max_output_tokens' => 256])
    ->withStreaming()
    ->stream()
    ->onPartialResponse(fn($partial) => print($partial->contentDelta));

$assembled = '';
$deltaCount = 0;

foreach ($stream->responses() as $partial) {
    $delta = $partial->contentDelta;
    if ($delta === '') {
        continue;
    }
    $deltaCount += 1;
    $assembled .= $delta;
}

$final = $stream->final();
assert($final !== null, 'Expected a final response');
$finalContent = $final->content();

echo "\nFinal response:\n{$finalContent}\n";

assert($deltaCount > 0, 'Expected at least one streamed delta');
assert($assembled !== '', 'Expected non-empty assembled content');
assert(Str::contains($assembled, $expectedPhrase, false), 'Expected phrase in streamed content');
assert(Str::contains($final->content(), $expectedPhrase, false), 'Expected phrase in final content');
assert(trim($assembled) === trim($finalContent), 'Expected assembled content to match final content');
?>
```
