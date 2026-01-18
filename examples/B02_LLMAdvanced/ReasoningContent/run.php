<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;

// EXAMPLE 1: regular API, allows to customize inference options
$response = (new Inference)
    //->withDebugPreset('on')
    //->wiretap(fn($e) => $e->print())
    ->using('deepseek-r')
    ->withMessages([['role' => 'user', 'content' => 'What is the capital of France. Answer with just a name.']])
    ->withMaxTokens(256)
    ->response();

echo "\nCASE #1: Sync response\n";
echo "USER: What is capital of France\n";
echo "ASSISTANT: {$response->content()}\n";
echo "REASONING: {$response->reasoningContent()}\n";
assert($response->content() !== '');
assert(Str::contains($response->content(), 'Paris'));
if ($response->reasoningContent() === '') {
    print("Note: reasoningContent is empty. This depends on model support and settings.\n");
}


// EXAMPLE 2: streaming response
$stream = (new Inference)
    //->withDebugPreset('on')
    //->wiretap(fn($e) => $e->print())
    ->using('deepseek-r') // optional, default is set in /config/llm.php
    ->with(
        messages: [['role' => 'user', 'content' => 'What is capital of Brasil. Answer with just a name.']],
        options: ['max_tokens' => 256]
    )
    ->withStreaming()
    ->stream();

echo "\nCASE #2: Streamed response\n";
echo "USER: What is capital of Brasil\n";
echo "ASSISTANT: ";
foreach ($stream->responses() as $partial) {
    echo $partial->contentDelta;
}
echo "\n";
echo "REASONING: {$stream->final()->reasoningContent()}\n";
assert($stream->final()->content() !== '');
assert(Str::contains($stream->final()->content(), 'Brasília'));
if ($stream->final()->reasoningContent() === '') {
    print("Note: reasoningContent is empty for streamed response. This depends on model support and settings.\n");
}
?>
