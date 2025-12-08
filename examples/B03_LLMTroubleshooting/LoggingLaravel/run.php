---
title: 'Embeddings Logging with Laravel'
docname: 'llm_logging_laravel'
path: ''
---

## Overview

Simple Embeddings operation logging with Laravel-style context.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Logging\Enrichers\LazyEnricher;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\PsrLoggerWriter;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Illuminate\Http\Request;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Mock Laravel request
$request = Request::create('/api/embeddings');
$request->headers->set('X-Request-ID', 'req_' . uniqid());

// Create logger
$logger = new Logger('embeddings');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Create pipeline with Laravel context
$pipeline = LoggingPipeline::create()
    ->filter(new LogLevelFilter('debug'))
    ->enrich(LazyEnricher::framework(fn() => [
        'request_id' => $request->headers->get('X-Request-ID'),
    ]))
    ->format(new MessageTemplateFormatter([
        \Cognesy\Polyglot\Embeddings\Events\EmbeddingsRequested::class =>
            '🔤 Embeddings requested: {provider}/{model} (Request: {framework.request_id})',
        \Cognesy\Polyglot\Embeddings\Events\EmbeddingsResponseReceived::class =>
            '✅ Embeddings generated: {dimensions}D vectors',
    ], channel: 'embeddings'))
    ->write(new PsrLoggerWriter($logger))
    ->build();

echo "📋 About to demonstrate Embeddings logging with Laravel context...\n\n";

// Create embeddings with logging
$embeddings = (new Embeddings)
    ->using('openai')
    ->wiretap($pipeline);

try {
    echo "🚀 Starting Embeddings generation...\n";
    $vectors = $embeddings
        ->withInputs([
            "The quick brown fox",
            "Jumps over the lazy dog"
        ])
        ->get();

    echo "\n✅ Embeddings completed!\n";
    echo "📊 Generated " . count($vectors->vectors()) . " embedding vectors\n";
    echo "📊 Vector dimensions: " . count($vectors->first()?->values() ?? []) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
```

```
// TODO: Add "Sample Output" section showing actual log messages
// Example format:
// ### Sample Output
// 📋 About to demonstrate Embeddings logging with Laravel...
// 🚀 Starting Embeddings request...
// [2025-12-07 01:18:13] embeddings.DEBUG: 🔄 [Laravel] Embeddings requested: openai/text-embedding-3-small
// [2025-12-07 01:18:14] embeddings.DEBUG: ✅ [Laravel] Embeddings completed: openai/text-embedding-3-small
// ✅ Embeddings completed!
// 📊 Generated 2 embedding vectors
// 📊 Vector dimensions: 1536
```
