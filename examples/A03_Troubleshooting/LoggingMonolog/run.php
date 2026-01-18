<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\MonologChannelWriter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Create Monolog logger
$logger = new Logger('instructor');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Create logging pipeline
$pipeline = LoggingPipeline::create()
    ->filter(new LogLevelFilter('debug'))
    ->format(new MessageTemplateFormatter([
        \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted::class =>
            '🎯 Starting extraction: {responseClass}',
        \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated::class =>
            '✅ Completed extraction: {responseClass}',
    ], channel: 'instructor'))
    ->write(new MonologChannelWriter($logger))
    ->build();

echo "📋 About to demonstrate Monolog logging with functional pipeline...\n\n";

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
// [2025-12-07T01:18:13.475202+00:00] instructor.DEBUG: 🎯 Starting extraction: User
// [2025-12-07T01:18:13.486832+00:00] instructor.DEBUG: HttpRequestSent
// [2025-12-07T01:18:14.640213+00:00] instructor.DEBUG: HttpResponseReceived
// [2025-12-07T01:18:14.659417+00:00] instructor.DEBUG: ✅ Completed extraction: User
?>
