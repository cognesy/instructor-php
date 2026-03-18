---
title: 'Inference Logging with Laravel'
docname: 'llm_logging_laravel_inference'
path: ''
id: 'a1c9'
tags:
  - 'logging'
  - 'laravel'
  - 'inference'
---
## Overview

Simple Inference operation logging using Monolog.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\MonologChannelWriter;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Create Monolog logger
$logger = new Logger('inference');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Create logging pipeline
$pipeline = LoggingPipeline::create()
    ->filter(new LogLevelFilter('debug'))
    ->format(new MessageTemplateFormatter([
        \Cognesy\Polyglot\Inference\Events\InferenceRequested::class => '🤖 Inference requested: {provider}/{model}',
        \Cognesy\Polyglot\Inference\Events\InferenceResponseCreated::class => '✅ Inference completed: {provider}/{model}',
    ], channel: 'inference'))
    ->write(new MonologChannelWriter($logger))
    ->build();

echo "📋 About to demonstrate Inference logging with Monolog...\n\n";

// Create inference with logging
$events = new EventDispatcher;
$events->wiretap($pipeline);
$inference = Inference::fromRuntime(InferenceRuntime::fromConfig(
    config: LLMConfig::fromPreset('openai'),
    events: $events,
));

echo "🚀 Starting Inference request...\n";
$response = $inference
    ->withMessages(Messages::fromString('What is the capital of France?'))
    ->withMaxTokens(50)
    ->get();

echo '📊 Response: '.($response ?: 'Empty response')."\n";

assert(!empty($response));
?>
```

```
// TODO: Add "Sample Output" section showing actual log messages
// Example format:
// ### Sample Output
// 📋 About to demonstrate Inference logging with Monolog...
// 🚀 Starting Inference request...
// [2025-12-07T01:18:13.475202+00:00] inference.DEBUG: 🤖 Inference requested: openai/gpt-4o-mini
// [2025-12-07T01:18:14.659417+00:00] inference.DEBUG: ✅ Inference completed: openai/gpt-4o-mini
// ✅ Inference completed!
// 📊 Response: The capital of France is Paris.
```
