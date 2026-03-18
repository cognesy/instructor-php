---
title: 'PSR-3 Logging with Functional Pipeline'
docname: 'logging_psr'
path: ''
id: '7cbd'
tags:
  - 'troubleshooting'
  - 'logging'
  - 'psr-3'
---
## Overview

Simple PSR-3 logging integration using Instructor's functional pipeline.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\PsrLoggerWriter;
use Cognesy\Polyglot\Inference\LLMProvider;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
// Create PSR-3 logger
$logger = new Logger('instructor');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Create logging pipeline - filters to only StructuredOutput events
$pipeline = LoggingPipeline::create()
    ->filter(new LogLevelFilter('debug'))
    ->format(new MessageTemplateFormatter([
        \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted::class =>
            '🎯 [PSR-3] Starting extraction: {responseClass}',
        \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated::class =>
            '✅ [PSR-3] Completed extraction: {responseClass}',
    ], channel: 'instructor'))
    ->write(new PsrLoggerWriter($logger))
    ->build();

echo "📋 About to demonstrate PSR-3 logging with functional pipeline...\n\n";

echo "🚀 Starting StructuredOutput extraction...\n";

class User
{
    public int $age;
    public string $name;
}

// Extract data with logging
$runtime = StructuredOutputRuntime::fromProvider(LLMProvider::using('openai'))
    ->wiretap($pipeline);

$user = (new StructuredOutput($runtime))
    ->withMessages("Jason is 25 years old.")
    ->withResponseClass(User::class)
    ->get();

echo "\n✅ Extraction completed!\n";
echo "📊 Result: User: {$user->name}, Age: {$user->age}\n";

assert($user->name === 'Jason');
assert($user->age === 25);

// TODO: Add "Sample Output" section showing actual log messages
// Example format:
// ### Sample Output
// [2025-12-07T01:18:13.475202+00:00] instructor.DEBUG: 🎯 [PSR-3] Starting extraction: User
// [2025-12-07T01:18:14.659417+00:00] instructor.DEBUG: ✅ [PSR-3] Completed extraction: User
?>
```
