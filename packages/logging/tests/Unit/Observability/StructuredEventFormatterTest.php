<?php

declare(strict_types=1);

use Cognesy\Events\Event;
use Cognesy\Logging\LogContext;
use Cognesy\Logging\Observability\StructuredEventFormatter;
use Psr\Log\LogLevel;

describe('StructuredEventFormatter', function () {
    it('prefers telemetry envelope correlation and merges payload convenience fields', function () {
        $event = new StructuredFormatterTestEvent([
            'executionId' => 'exec-top-level',
            'requestId' => 'req-top-level',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'method' => 'POST',
            'telemetry' => [
                'correlation' => [
                    'root_operation_id' => 'root-123',
                    'parent_operation_id' => 'parent-456',
                    'request_id' => 'req-telemetry',
                ],
            ],
        ]);

        $entry = (new StructuredEventFormatter('polyglot.inference.runtime'))(
            $event,
            LogContext::fromEvent($event),
        );

        expect($entry->context['correlation'])->toBe([
            'root_operation_id' => 'root-123',
            'parent_operation_id' => 'parent-456',
            'request_id' => 'req-top-level',
            'execution_id' => 'exec-top-level',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'method' => 'POST',
        ]);
    });

    it('extracts normalized correlation from camelCase payloads without telemetry', function () {
        $event = new StructuredFormatterTestEvent([
            'agentId' => 'agent-1',
            'executionId' => 'exec-1',
            'parentAgentId' => 'parent-1',
            'attemptId' => 'attempt-1',
            'phaseId' => 'phase-1',
            'agentType' => 'codex',
            'statusCode' => 200,
            'durationMs' => 123.4,
            'step' => 2,
        ]);

        $entry = (new StructuredEventFormatter('agent-loop'))(
            $event,
            LogContext::fromEvent($event),
        );

        expect($entry->context['correlation'])->toBe([
            'execution_id' => 'exec-1',
            'attempt_id' => 'attempt-1',
            'phase_id' => 'phase-1',
            'agent_id' => 'agent-1',
            'parent_agent_id' => 'parent-1',
            'agent_type' => 'codex',
            'status' => 200,
            'duration_ms' => 123.4,
            'step' => 2,
        ]);
    });

    it('keeps payload and package metadata intact', function () {
        $event = new StructuredFormatterTestEvent(['requestId' => 'req-1']);

        $entry = (new StructuredEventFormatter('instructor.structured-output.runtime'))(
            $event,
            LogContext::fromEvent($event),
        );

        expect($entry->context['package'])->toBe('instructor')
            ->and($entry->context['component'])->toBe('instructor.structured-output.runtime')
            ->and($entry->context['payload'])->toBe(['requestId' => 'req-1']);
    });

    it('can omit sections and clip payload strings', function () {
        $event = new StructuredFormatterTestEvent([
            'requestId' => 'req-123456',
            'nested' => ['message' => 'abcdefghijklmnopqrstuvwxyz'],
        ]);

        $entry = (new StructuredEventFormatter(
            component: 'instructor.structured-output.runtime',
            includeCorrelation: false,
            includeComponentMetadata: false,
            stringClipLength: 5,
        ))(
            $event,
            LogContext::fromEvent($event),
        );

        expect(array_keys($entry->context))->toBe(['event_id', 'event_class', 'event_name', 'payload'])
            ->and($entry->context['payload'])->toBe([
                'requestId' => 'req-1',
                'nested' => ['message' => 'abcde'],
            ]);
    });
});

class StructuredFormatterTestEvent extends Event
{
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->logLevel = LogLevel::INFO;
    }

    public function name(): string
    {
        return 'StructuredFormatterTestEvent';
    }
}
