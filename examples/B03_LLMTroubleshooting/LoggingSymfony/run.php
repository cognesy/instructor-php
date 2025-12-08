---
title: 'Inference Logging with Symfony'
docname: 'llm_logging_symfony'
path: ''
---

## Overview

Inference operation logging with Symfony-style context.

## Example

<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Enrichers\LazyEnricher;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Writers\PsrLoggerWriter;
use Symfony\Component\HttpFoundation\Request;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Mock Symfony request
$request = Request::create('/api/stream');
$request->attributes->set('_route', 'api.stream');

// Create logger
$logger = new Logger('inference');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Create pipeline with Symfony context
$pipeline = LoggingPipeline::create()
    ->filter(new LogLevelFilter('debug'))
    ->enrich(LazyEnricher::framework(fn() => [
        'route' => $request->attributes->get('_route'),
    ]))
    ->format(new MessageTemplateFormatter([
        \Cognesy\Polyglot\Inference\Events\InferenceRequested::class =>
            '🤖 [SYMFONY] Inference requested: {provider}/{model} (Route: {framework.route})',
        \Cognesy\Polyglot\Inference\Events\InferenceResponseCreated::class =>
            '✅ [SYMFONY] Inference completed: {provider}/{model}',
    ], channel: 'inference'))
    ->write(new PsrLoggerWriter($logger))
    ->build();

echo "📋 About to demonstrate Inference logging with Symfony...\n\n";

// Create inference with logging
$inference = (new Inference)
    ->using('openai')
    ->wiretap($pipeline);

try {
    echo "🚀 Starting simple Inference to demonstrate logging...\n";

    $response = $inference
        ->withMessages([
            ['role' => 'user', 'content' => 'What is the capital of France?']
        ])
        ->withMaxTokens(50)
        ->get();

    echo "\n✅ Inference completed!\n";

    // Handle response properly - it might be a string or object
    if (is_string($response)) {
        echo "📊 Response: " . ($response ?: "Empty response") . "\n";
    } else {
        echo "📊 Response: " . ($response->content ?? "Response object has no content property") . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// TODO: Add "Sample Output" section showing actual log messages
// Example format:
// ### Sample Output
// ```
// 📋 About to demonstrate Inference logging with Symfony...
// 🚀 Starting Inference request...
// [2025-12-07 01:18:13] inference.DEBUG: 🔄 [Symfony] Inference requested: openai/gpt-4o-mini
// [2025-12-07 01:18:14] inference.DEBUG: ✅ [Symfony] Inference completed: openai/gpt-4o-mini
// ✅ Inference completed!
// 📊 Response: The capital of France is Paris.
// ```