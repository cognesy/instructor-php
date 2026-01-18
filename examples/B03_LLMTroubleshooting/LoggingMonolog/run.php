<?php
require 'examples/boot.php';

use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\MonologChannelWriter;
use Cognesy\Polyglot\Inference\Inference;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Create Monolog logger
$logger = new Logger('inference');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Create logging pipeline
$pipeline = LoggingPipeline::create()
    ->filter(new LogLevelFilter('debug'))
    ->format(new MessageTemplateFormatter([
        \Cognesy\Polyglot\Inference\Events\InferenceRequested::class =>
            '🤖 Inference requested: {provider}/{model}',
        \Cognesy\Polyglot\Inference\Events\InferenceResponseCreated::class =>
            '✅ Inference completed: {provider}/{model}',
    ], channel: 'inference'))
    ->write(new MonologChannelWriter($logger))
    ->build();

echo "📋 About to demonstrate Inference logging with Monolog...\n\n";

// Create inference with logging
$inference = (new Inference)
    ->using('openai')
    ->wiretap($pipeline);

echo "🚀 Starting Inference request...\n";
$response = $inference
    ->withMessages([
        ['role' => 'user', 'content' => 'What is the capital of France?']
    ])
    ->withMaxTokens(50)
    ->get();

echo "📊 Response: " . ($response ?: "Empty response") . "\n";
?>
