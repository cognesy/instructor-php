<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\Usage;

class User {
    public int $age;
    public string $name;
}

function printUsage(Usage $usage) : void {
    echo "Input tokens: $usage->inputTokens\n";
    echo "Output tokens: $usage->outputTokens\n";
    echo "Cache creation tokens: $usage->cacheWriteTokens\n";
    echo "Cache read tokens: $usage->cacheReadTokens\n";
    echo "Reasoning tokens: $usage->reasoningTokens\n";
}

echo "COUNTING TOKENS FOR SYNC RESPONSE\n";
$text = "Jason is 25 years old and works as an engineer.";
$response = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: User::class,
    )->response();

echo "\nTEXT: $text\n";
assert($response->usage()->total() > 0);
printUsage($response->usage());


echo "\n\nCOUNTING TOKENS FOR STREAMED RESPONSE\n";
$text = "Anna is 19 years old.";
$stream = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: User::class,
        options: ['stream' => true],
    )
    ->stream();

// Consume the stream to get final usage
foreach ($stream->partials() as $partial) {
    // streaming...
}
echo "\nTEXT: $text\n";
assert($stream->usage()->total() > 0);
printUsage($stream->usage());
?>
