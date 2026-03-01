# Metrics Package

Event-driven metrics collection for InstructorPHP.

Use it to:
- collect metrics from event listeners
- store them in a registry
- export them to logs or custom backends

## Example

```php
<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Metrics\Data\Tags;
use Cognesy\Metrics\Exporters\CallbackExporter;
use Cognesy\Metrics\Metrics;

$metrics = (new Metrics(new EventDispatcher()))
    ->exportTo(new CallbackExporter(function (iterable $items): void {
        foreach ($items as $metric) {
            // send metric to your backend
        }
    }));

$metrics->registry()->counter('requests_total', Tags::of(['route' => '/health']));
$metrics->export();
```

## Documentation

- `packages/metrics/CHEATSHEET.md`
- `packages/metrics/src/`
