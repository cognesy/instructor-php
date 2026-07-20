---
title: 'Observe StructuredOutput events, logs, and telemetry'
docname: 'observability_telemetry_mechanisms'
id: '8a63'
tags:
  - 'troubleshooting'
  - 'observability'
  - 'logging'
  - 'telemetry'
---
## Overview

This example proves the local observability mechanisms that are useful when a
structured-output call has to be debugged in production.

It runs one deterministic invalid-then-corrected response through the real
StructuredOutput pipeline and records the same execution through four surfaces:

- `wiretap()`: receives every runtime event in process
- `onEvent()`: receives a specific event class
- `EventLog::enable()` plus `EventLog::root()`: writes structured JSONL logs
- `RuntimeEventBridge`: projects runtime events into telemetry observations

The example uses a local recording inference driver and a local telemetry
exporter, so it does not require provider credentials or a Langfuse/Logfire
account.

## Example

```php
<?php
require 'examples/boot.php';
require_once 'examples/_support/eventlog_readback.php';

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Events\Attempt\ResponseRetryScheduled;
use Cognesy\Instructor\Events\Response\ResponseValidated;
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Telemetry\InstructorTelemetryProjector;
use Cognesy\Logging\EventLog;
use Cognesy\Logging\Filters\EventClassFilter;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\LogEntry;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\CallableWriter;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Observation\Observation;
use Symfony\Component\Validator\Constraints as Assert;

final class ObservableIncident
{
    public function __construct(
        #[Assert\NotBlank]
        public string $service,

        #[Assert\Choice(choices: ['low', 'medium', 'high', 'critical'])]
        public string $severity,

        #[Assert\Choice(choices: ['open', 'triaged', 'closed'])]
        public string $status,

        #[Assert\DateTime(format: DateTimeInterface::ATOM)]
        public string $reportedAt,
    ) {}
}

final class ObservabilityRecordingDriver implements CanProcessInferenceRequest
{
    /** @var list<InferenceResponse> */
    private array $responses;

    /** @var list<array<int, array<string, mixed>>> */
    public array $requests = [];

    /** @param list<array<string, mixed>> $responses */
    public function __construct(array $responses)
    {
        $this->responses = array_map(
            static fn(array $response): InferenceResponse => new InferenceResponse(
                content: json_encode($response, JSON_THROW_ON_ERROR),
                finishReason: 'stop',
            ),
            $responses,
        );
    }

    public function makeResponseFor(InferenceRequest $request): InferenceResponse
    {
        $this->requests[] = $request->messages()->toArray();

        if ($this->responses === []) {
            return new InferenceResponse(content: '{}', finishReason: 'stop');
        }

        return array_shift($this->responses);
    }

    /** @return iterable<PartialInferenceDelta> */
    public function makeStreamDeltasFor(InferenceRequest $request): iterable
    {
        return [];
    }
}

final class RecordingTelemetryExporter implements CanExportObservations
{
    /** @var list<Observation> */
    public array $observations = [];

    public function exportObservation(Observation $observation): void
    {
        $this->observations[] = $observation;
    }
}

function observabilityInput(): string
{
    return <<<TEXT
Customer report copied from Slack:
- "checkout blew up after payment, probably urgent"
- somebody set ticket status to "needs attention"
- timestamp in the support tool says "today at 09:10 UTC"

Extract the incident for the internal workflow. The workflow only accepts
severity low, medium, high, critical; status open, triaged, closed; and an
ISO-8601 timestamp.
TEXT;
}

function countEvents(array $events, string $class): int
{
    return count(array_filter(
        $events,
        static fn(object $event): bool => $event instanceof $class,
    ));
}

function telemetryNames(RecordingTelemetryExporter $exporter): array
{
    return array_map(
        static fn(Observation $observation): string => $observation->name(),
        $exporter->observations,
    );
}

$driver = new ObservabilityRecordingDriver([
    [
        'service' => 'Checkout',
        'severity' => 'urgent',
        'status' => 'needs attention',
        'reportedAt' => 'today at 09:10 UTC',
    ],
    [
        'service' => 'Checkout',
        'severity' => 'critical',
        'status' => 'open',
        'reportedAt' => '2026-07-11T09:10:00+00:00',
    ],
]);

$eventLogPath = ExampleEventLog::path('examples-a03-observability-telemetry');
$wiretappedEvents = [];
$targetedFailures = [];
$pipelineEntries = [];
$telemetryExporter = new RecordingTelemetryExporter();
$telemetry = new Telemetry(
    registry: new TraceRegistry(),
    exporter: $telemetryExporter,
);

EventLog::enable($eventLogPath);

try {
    $events = EventLog::root('examples.a03.observability-telemetry');
    if (!$events instanceof EventDispatcher) {
        throw new RuntimeException('Expected EventLog::root() to return EventDispatcher in this example.');
    }

    $events->wiretap(static function(object $event) use (&$wiretappedEvents): void {
        $wiretappedEvents[] = $event;
    });

    $events->addListener(
        ResponseValidationFailed::class,
        static function(ResponseValidationFailed $event) use (&$targetedFailures): void {
            $targetedFailures[] = $event;
        },
    );

    $loggingPipeline = LoggingPipeline::create()
        ->filter(new EventClassFilter(includedClasses: [
            ResponseValidationFailed::class,
            ResponseRetryScheduled::class,
            StructuredOutputResponseGenerated::class,
        ]))
        ->format(new MessageTemplateFormatter([
            ResponseValidationFailed::class => 'structured output validation failed',
            ResponseRetryScheduled::class => 'structured output retry requested',
            StructuredOutputResponseGenerated::class => 'structured output accepted',
        ]))
        ->write(CallableWriter::create(
            static function(LogEntry $entry) use (&$pipelineEntries): void {
                $pipelineEntries[] = $entry;
            },
        ))
        ->build();
    $events->wiretap($loggingPipeline);

    (new RuntimeEventBridge(new CompositeTelemetryProjector([
        new InstructorTelemetryProjector($telemetry),
        new PolyglotTelemetryProjector($telemetry),
    ])))->attachTo($events);

    $runtime = StructuredOutputRuntime::fromProvider(
        provider: LLMProvider::new()->withDriver($driver),
        events: $events,
    )
        ->withOutputMode(OutputMode::Json)
        ->withMaxRetries(2);

    $incident = (new StructuredOutput($runtime))
        ->with(
            messages: observabilityInput(),
            responseModel: ObservableIncident::class,
            model: 'gpt-4o-mini',
        )
        ->get();

    $eventLogEntries = ExampleEventLog::read($eventLogPath);
} finally {
    EventLog::disable();
}

if (!$incident instanceof ObservableIncident) {
    throw new RuntimeException('Expected ObservableIncident.');
}

$telemetry->flush();
$names = telemetryNames($telemetryExporter);

echo "=== Accepted incident ===\n";
echo json_encode(get_object_vars($incident), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "=== Event callbacks ===\n";
echo 'Wiretapped events: ' . count($wiretappedEvents) . "\n";
echo 'Targeted validation failures: ' . count($targetedFailures) . "\n";
echo 'Validation failure events: ' . countEvents($wiretappedEvents, ResponseValidationFailed::class) . "\n";
echo 'Retry events: ' . countEvents($wiretappedEvents, ResponseRetryScheduled::class) . "\n";
echo 'Validated events: ' . countEvents($wiretappedEvents, ResponseValidated::class) . "\n\n";

echo "=== Logs ===\n";
echo 'JSONL path: ' . $eventLogPath . "\n";
echo 'JSONL entries: ' . count($eventLogEntries) . "\n";
echo 'Pipeline entries: ' . count($pipelineEntries) . "\n";
echo 'Pipeline messages: ' . implode(' | ', array_map(
    static fn(LogEntry $entry): string => $entry->message,
    $pipelineEntries,
)) . "\n\n";

echo "=== Telemetry ===\n";
echo 'Observations exported: ' . count($telemetryExporter->observations) . "\n";
echo 'Observation names: ' . implode(' | ', $names) . "\n";
echo 'Materialization-failure telemetry observation: ' . (in_array('structured_output.response_materialization_failed', $names, true) ? 'yes' : 'no') . "\n";

if ($incident->severity !== 'critical' || $incident->status !== 'open') {
    throw new RuntimeException('Expected repaired incident values.');
}
if (count($driver->requests) !== 2) {
    throw new RuntimeException('Expected one original request and one retry request.');
}
if (count($targetedFailures) !== 1) {
    throw new RuntimeException('Expected onEvent-style targeted validation failure callback.');
}
if (countEvents($wiretappedEvents, ResponseRetryScheduled::class) !== 1) {
    throw new RuntimeException('Expected retry event in wiretap stream.');
}
if ($eventLogEntries === []) {
    throw new RuntimeException('Expected JSONL EventLog entries.');
}
if (count($pipelineEntries) < 3) {
    throw new RuntimeException('Expected validation, retry, and accepted log pipeline entries.');
}
if (!in_array('structured_output.execute', $names, true)) {
    throw new RuntimeException('Expected telemetry structured-output span observation.');
}
if (!in_array('structured_output.response_materialization_failed', $names, true)) {
    throw new RuntimeException('Expected canonical materialization-failure telemetry observation.');
}
?>
```
