<?php

declare(strict_types=1);

use Cognesy\Events\Event;
use Cognesy\Logging\Config\EventLogConfig;
use Cognesy\Logging\EventLog;
use Psr\Log\LogLevel;

describe('EventLog', function () {
    afterEach(function () {
        EventLog::disable();
        putenv('INSTRUCTOR_LOG_PATH');
        putenv('INSTRUCTOR_LOG_LEVEL');
        unset($_ENV['INSTRUCTOR_LOG_PATH'], $_ENV['INSTRUCTOR_LOG_LEVEL']);
    });

    it('writes using the configured formatter knobs', function () {
        $path = tempnam(sys_get_temp_dir(), 'event-log-');
        EventLog::enable(new EventLogConfig(
            path: $path,
            includePayload: false,
            includeCorrelation: false,
            includeComponentMetadata: false,
        ));

        EventLog::root('logging.test')->dispatch(new EventLogIncludedTestEvent([
            'requestId' => 'req-1',
            'message' => 'abcdefghijklmnopqrstuvwxyz',
        ]));

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entry = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);

        expect($entry['channel'])->toBe('logging.test')
            ->and(array_keys($entry['context']))->toBe(['event_id', 'event_class', 'event_name']);
    });

    it('filters included and excluded events from config', function () {
        $path = tempnam(sys_get_temp_dir(), 'event-log-');
        EventLog::enable(new EventLogConfig(
            path: $path,
            level: LogLevel::DEBUG,
            includeEvents: [EventLogIncludedTestEvent::class],
            excludeEvents: [EventLogExcludedTestEvent::class],
        ));

        $events = EventLog::root('logging.test');
        $events->dispatch(new EventLogIncludedTestEvent(['requestId' => 'keep-me']));
        $events->dispatch(new EventLogExcludedTestEvent(['requestId' => 'drop-me']));

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = array_map(
            static fn(string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            $lines,
        );

        expect($entries)->toHaveCount(1)
            ->and($entries[0]['message'])->toBe('EventLogIncludedTestEvent')
            ->and($entries[0]['context']['payload'])->toBe(['requestId' => 'keep-me']);
    });
});

class EventLogIncludedTestEvent extends Event
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->logLevel = LogLevel::INFO;
    }

    public function name(): string
    {
        return 'EventLogIncludedTestEvent';
    }
}

class EventLogExcludedTestEvent extends Event
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->logLevel = LogLevel::INFO;
    }

    public function name(): string
    {
        return 'EventLogExcludedTestEvent';
    }
}
