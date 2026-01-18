<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Logging\Enrichers\LazyEnricher;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\PsrLoggerWriter;
use Illuminate\Http\Request;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Mock Laravel request
$request = Request::create('/api/extract');
$request->headers->set('X-Request-ID', 'req_' . uniqid());

// Create logger
$logger = new Logger('instructor');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Create pipeline with request context
$pipeline = LoggingPipeline::create()
    ->filter(new LogLevelFilter('debug'))  // Changed to debug to capture more events
    ->enrich(LazyEnricher::framework(fn() => [
        'request_id' => $request->headers->get('X-Request-ID'),
        'route' => '/api/extract',
    ]))
    ->format(new MessageTemplateFormatter([
        \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted::class =>
            '🎯 [LARAVEL] Starting extraction: {responseClass} (Request: {framework.request_id})',
        \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated::class =>
            '✅ [LARAVEL] Completed extraction: {responseClass} (Request: {framework.request_id})',
    ], channel: 'instructor'))
    ->write(new PsrLoggerWriter($logger))
    ->build();

echo "🔧 Laravel logging pipeline configured\n";
echo "📋 About to execute StructuredOutput with logging...\n\n";

class User
{
    public int $age;
    public string $name;
}

// Extract data with logging
echo "🚀 Starting StructuredOutput extraction...\n";
$user = (new StructuredOutput)
    ->using('openai')
    ->wiretap($pipeline)
    ->withMessages("Jason is 25 years old.")
    ->withResponseClass(User::class)
    ->get();

echo "\n✅ Extraction completed!\n";
echo "📊 Result: User: {$user->name}, Age: {$user->age}\n";

// TODO: Add "Sample Output" section showing actual log messages
// Example format:
// ### Sample Output
// [2025-12-07 01:18:13] instructor.DEBUG: 🔄 [Laravel] Starting extraction: User
// [2025-12-07 01:18:14] instructor.DEBUG: ✅ [Laravel] Completed extraction: User
?>
