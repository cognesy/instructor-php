# Instructor Logging

A functional logging pipeline for Instructor PHP with seamless Laravel and Symfony integration.

## Overview

This package provides a clean, scalable logging solution that builds on Instructor's existing event-driven architecture. Instead of polluting domain classes with logging concerns, it uses a functional pipeline approach that composes filters, enrichers, formatters, and writers.

## Key Features

- **🔧 Functional Architecture**: Pure functions that compose like Lego blocks
- **⚡ Lazy Evaluation**: Context only computed when needed
- **🏗️ Framework Integration**: Auto-configuration for Laravel and Symfony
- **📊 Zero Domain Pollution**: Business logic stays pure
- **🎯 Event Hierarchy Support**: Filter by event families (HTTP, StructuredOutput, etc.)
- **🔄 Composable Components**: Mix and match filters, enrichers, formatters

## Installation

```bash
composer require cognesy/logging
```

## Usage Examples

### Basic Usage (Framework-Agnostic)

```php
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Writers\PsrLoggerWriter;
use Psr\Log\LogLevel;

// Create a logging pipeline
$pipeline = LoggingPipeline::create()
    ->filter(new LogLevelFilter(LogLevel::INFO))
    ->write(new PsrLoggerWriter($logger))
    ->build();

// Apply to any Instructor class
$structuredOutput = new StructuredOutput();
$structuredOutput->wiretap($pipeline);
```

### Advanced Pipeline Configuration

```php
use Cognesy\Logging\Enrichers\LazyEnricher;
use Cognesy\Logging\Filters\EventHierarchyFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;

$pipeline = LoggingPipeline::create()
    // Filter only HTTP events with WARNING level or above
    ->filter(EventHierarchyFilter::httpEventsOnly())
    ->filter(new LogLevelFilter(LogLevel::WARNING))

    // Add lazy context enrichment
    ->enrich(LazyEnricher::framework(fn() => [
        'request_id' => request()->header('X-Request-ID'),
        'user_id' => auth()->id(),
    ]))

    // Custom message templates
    ->format(new MessageTemplateFormatter([
        HttpRequestSent::class => 'HTTP {method} {url} → {status_code}',
        StructuredOutputStarted::class => 'Generating {responseClass} with {model}',
    ]))

    ->write(new PsrLoggerWriter($logger))
    ->build();
```

### Laravel Auto-Configuration

```php
// In your controller - logging is automatically configured
class UserController extends Controller
{
    public function extract(Request $request, StructuredOutput $structuredOutput): JsonResponse
    {
        $user = $structuredOutput
            ->withMessages($request->input('text'))
            ->withResponseClass(User::class)
            ->get(); // Automatically logged with request context

        return response()->json($user);
    }
}
```

## Architecture

### Pipeline Stages

```
Event → Filters → Enrichers → Formatters → Writers
```

1. **Filters**: Determine if an event should be logged
2. **Enrichers**: Add contextual data (request info, user data, metrics)
3. **Formatters**: Convert events to log entries with messages
4. **Writers**: Output to destinations (PSR-3, Monolog, files)

## Benefits vs Traditional Approaches

### ❌ Traditional Problems
- Domain classes polluted with logging logic
- Framework-specific ServiceProvider explosion
- Stateful services prevent composition
- Eager context evaluation wastes CPU

### ✅ Functional Pipeline Benefits
- **Zero domain pollution**: Business logic stays pure
- **Infinite scalability**: New framework = 20 lines, new class = 0 lines
- **Composable**: Mix filters/enrichers like Lego blocks
- **Performance**: Lazy evaluation, pure functions
- **Testable**: No mocking required

## Testing

```bash
cd packages/logging
composer install
composer test
```

## License

MIT License.