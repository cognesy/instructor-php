---
title: 'Read StructuredOutput EventLog JSONL'
docname: 'structured_output_eventlog_readback'
id: 'c48e'
tags:
  - 'troubleshooting'
  - 'eventlog'
  - 'jsonl'
---
## Overview

This example shows the smallest opt-in file logging flow with `EventLog::enable()`.
It activates the default JSONL sink, runs one `StructuredOutput` request without
passing a custom event bus, then reads the generated log file back and prints the
captured entries on screen.

Key concepts:
- `EventLog::enable()`: turns on the default JSONL sink for the current process
- `StructuredOutputRuntime::fromProvider(...)`: uses the default runtime event bus
- JSONL readback: parse the generated file after the request completes

## Example

```php
<?php
require 'examples/boot.php';
require_once 'examples/_support/eventlog_readback.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Logging\EventLog;
use Cognesy\Polyglot\Inference\LLMProvider;

final class IncidentSummary
{
    public string $severity;
    public string $summary;
}

$logPath = ExampleEventLog::path('examples-a03-structured-output-eventlog');

EventLog::enable($logPath);

try {
    $runtime = StructuredOutputRuntime::fromProvider(LLMProvider::using('openai'));

    $incident = (new StructuredOutput($runtime))
        ->with(
            messages: 'Customer report: checkout returns HTTP 500 after payment. Mark severity and summarize it in one sentence.',
            responseModel: IncidentSummary::class,
        )
        ->get();

    $entries = ExampleEventLog::read($logPath);
} finally {
    EventLog::disable();
}

echo "=== StructuredOutput Result ===\n";
echo "Severity: {$incident->severity}\n";
echo "Summary: {$incident->summary}\n";

echo "\n=== EventLog Entries ===\n";
echo "Log file: {$logPath}\n";
echo 'Entries captured: ' . count($entries) . "\n\n";

ExampleEventLog::print($entries, 8);

assert($incident->severity !== '');
assert($incident->summary !== '');
assert($entries !== []);
?>
```
