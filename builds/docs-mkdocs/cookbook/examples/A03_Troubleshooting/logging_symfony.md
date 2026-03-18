---
title: 'Symfony Logging Integration'
docname: 'logging_symfony'
path: ''
id: '7ab5'
tags:
  - 'troubleshooting'
  - 'logging'
  - 'symfony'
---
## Overview

Symfony integration with Instructor's functional logging pipeline.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Logging\Enrichers\LazyEnricher;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\PsrLoggerWriter;
use Cognesy\Polyglot\Inference\LLMProvider;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
// Mock Symfony container and request
$container = new Container();
$request = Request::create('/api/extract');
$request->headers->set('X-Request-ID', 'req_' . uniqid());
$request->attributes->set('_route', 'api.extract');

// Create logger
$logger = new Logger('instructor');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Create pipeline with Symfony context
$pipeline = LoggingPipeline::create()
    ->filter(new LogLevelFilter('debug'))
    ->enrich(LazyEnricher::framework(fn() => [
        'request_id' => $request->headers->get('X-Request-ID'),
        'route' => $request->attributes->get('_route'),
    ]))
    ->format(new MessageTemplateFormatter([
        \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted::class =>
            '🎯 [SYMFONY] Starting extraction: {responseClass} (Route: {framework.route})',
        \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated::class =>
            '✅ [SYMFONY] Completed extraction: {responseClass}',
    ], channel: 'instructor'))
    ->write(new PsrLoggerWriter($logger))
    ->build();

echo "🔧 Symfony logging pipeline configured\n";
echo "📋 About to execute StructuredOutput with logging...\n\n";

class User
{
    public int $age;
    public string $name;
}

// Extract data with logging
echo "🚀 Starting StructuredOutput extraction...\n";
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
// [2025-12-07 01:18:13] instructor.DEBUG: 🔄 [Symfony] Starting extraction: User
// [2025-12-07 01:18:14] instructor.DEBUG: ✅ [Symfony] Completed extraction: User
?>
```
