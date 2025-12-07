<?php

declare(strict_types=1);

use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventFilter;
use Cognesy\Logging\Contracts\EventEnricher;
use Cognesy\Logging\Contracts\EventFormatter;
use Cognesy\Logging\Contracts\LogWriter;
use Cognesy\Logging\Enrichers\BaseEnricher;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Formatters\DefaultFormatter;
use Cognesy\Logging\LogContext;
use Cognesy\Logging\LogEntry;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\CallableWriter;
use Psr\Log\LogLevel;

describe('LoggingPipeline', function () {
    it('builds functional pipeline', function () {
        $logged = [];

        $pipeline = LoggingPipeline::create()
            ->filter(new LogLevelFilter(LogLevel::INFO))
            ->enrich(new BaseEnricher())
            ->format(new DefaultFormatter())
            ->write(CallableWriter::create(function (LogEntry $entry) use (&$logged) {
                $logged[] = $entry;
            }))
            ->build();

        $event = new PipelineTestEvent(LogLevel::INFO, ['test' => 'data']);
        $pipeline($event);

        expect($logged)->toHaveCount(1)
            ->and($logged[0])->toBeInstanceOf(LogEntry::class)
            ->and($logged[0]->level)->toBe(LogLevel::INFO);
    });

    it('filters out events that do not pass filters', function () {
        $logged = [];

        $pipeline = LoggingPipeline::create()
            ->filter(new LogLevelFilter(LogLevel::ERROR))
            ->write(CallableWriter::create(function (LogEntry $entry) use (&$logged) {
                $logged[] = $entry;
            }))
            ->build();

        $debugEvent = new PipelineTestEvent(LogLevel::DEBUG);
        $errorEvent = new PipelineTestEvent(LogLevel::ERROR);

        $pipeline($debugEvent);
        $pipeline($errorEvent);

        expect($logged)->toHaveCount(1)
            ->and($logged[0]->level)->toBe(LogLevel::ERROR);
    });

    it('applies multiple filters with AND logic', function () {
        $logged = [];

        $pipeline = LoggingPipeline::create()
            ->filter(new LogLevelFilter(LogLevel::INFO))
            ->filter(new class implements EventFilter {
                public function __invoke(Event $event): bool
                {
                    return $event->data['allowed'] ?? false;
                }
            })
            ->write(CallableWriter::create(function (LogEntry $entry) use (&$logged) {
                $logged[] = $entry;
            }))
            ->build();

        $allowedInfo = new PipelineTestEvent(LogLevel::INFO, ['allowed' => true]);
        $blockedInfo = new PipelineTestEvent(LogLevel::INFO, ['allowed' => false]);
        $allowedDebug = new PipelineTestEvent(LogLevel::DEBUG, ['allowed' => true]);

        $pipeline($allowedInfo);
        $pipeline($blockedInfo);
        $pipeline($allowedDebug);

        expect($logged)->toHaveCount(1);
    });

    it('combines multiple enrichers', function () {
        $logged = [];

        $pipeline = LoggingPipeline::create()
            ->enrich(new class implements EventEnricher {
                public function __invoke(Event $event): LogContext
                {
                    return LogContext::fromEvent($event)->withFrameworkContext(['request_id' => 'req_123']);
                }
            })
            ->enrich(new class implements EventEnricher {
                public function __invoke(Event $event): LogContext
                {
                    return LogContext::fromEvent($event)->withUserContext(['user_id' => 'user_456']);
                }
            })
            ->write(CallableWriter::create(function (LogEntry $entry) use (&$logged) {
                $logged[] = $entry;
            }))
            ->build();

        $event = new PipelineTestEvent(LogLevel::INFO);
        $pipeline($event);

        expect($logged)->toHaveCount(1)
            ->and($logged[0]->context['framework']['request_id'])->toBe('req_123')
            ->and($logged[0]->context['user']['user_id'])->toBe('user_456');
    });

    it('applies formatters in sequence', function () {
        $logged = [];

        $pipeline = LoggingPipeline::create()
            ->enrich(new BaseEnricher())
            ->format(new class implements EventFormatter {
                public function __invoke(Event $event, LogContext $context): LogEntry
                {
                    return LogEntry::create(LogLevel::INFO, 'First formatter', $context->toArray());
                }
            })
            ->format(new class implements EventFormatter {
                public function __invoke(Event $event, LogContext $context): LogEntry
                {
                    return LogEntry::create(LogLevel::ERROR, 'Second formatter', []);
                }
            })
            ->write(CallableWriter::create(function (LogEntry $entry) use (&$logged) {
                $logged[] = $entry;
            }))
            ->build();

        $event = new PipelineTestEvent(LogLevel::DEBUG);
        $pipeline($event);

        expect($logged)->toHaveCount(1)
            ->and($logged[0]->message)->toBe('Second formatter')
            ->and($logged[0]->level)->toBe(LogLevel::ERROR);
    });

    it('writes to multiple writers', function () {
        $log1 = [];
        $log2 = [];

        $pipeline = LoggingPipeline::create()
            ->write(CallableWriter::create(function (LogEntry $entry) use (&$log1) {
                $log1[] = $entry;
            }))
            ->write(CallableWriter::create(function (LogEntry $entry) use (&$log2) {
                $log2[] = $entry;
            }))
            ->build();

        $event = new PipelineTestEvent(LogLevel::INFO);
        $pipeline($event);

        expect($log1)->toHaveCount(1)
            ->and($log2)->toHaveCount(1)
            ->and($log1[0])->toBe($log2[0]);
    });

    it('handles empty pipeline gracefully', function () {
        $logged = [];

        $pipeline = LoggingPipeline::create()
            ->write(CallableWriter::create(function (LogEntry $entry) use (&$logged) {
                $logged[] = $entry;
            }))
            ->build();

        $event = new PipelineTestEvent(LogLevel::WARNING, 'test');
        $pipeline($event);

        expect($logged)->toHaveCount(1)
            ->and($logged[0]->level)->toBe(LogLevel::WARNING)
            ->and($logged[0]->message)->toBe('TestEvent');
    });
});

class PipelineTestEvent extends Event
{
    public function __construct(string $logLevel = LogLevel::DEBUG, mixed $data = null)
    {
        parent::__construct($data);
        $this->logLevel = $logLevel;
    }

    public function name(): string
    {
        return 'TestEvent';
    }
}