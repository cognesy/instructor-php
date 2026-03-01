# Logging Package

Functional logging pipeline for InstructorPHP events.

Use it to turn events into structured log entries using composable:
- filters
- enrichers
- formatters
- writers

Laravel and Symfony integrations are included.

## Example

```php
<?php

use Cognesy\Events\Event;
use Cognesy\Logging\Enrichers\BaseEnricher;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\DefaultFormatter;
use Cognesy\Logging\LogEntry;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\CallableWriter;
use Psr\Log\LogLevel;

$pipeline = LoggingPipeline::create()
    ->filter(new LogLevelFilter(LogLevel::INFO))
    ->enrich(new BaseEnricher())
    ->format(new DefaultFormatter())
    ->write(CallableWriter::create(function (LogEntry $entry): void {
        error_log($entry->message);
    }))
    ->build();

$pipeline(new Event(['operation' => 'demo']));
```

## Documentation

- `packages/logging/CHEATSHEET.md`
- `packages/logging/src/`
- `packages/logging/tests/`
