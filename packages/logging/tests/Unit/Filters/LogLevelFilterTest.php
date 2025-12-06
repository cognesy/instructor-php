<?php

declare(strict_types=1);

use Cognesy\Events\Event;
use Cognesy\Logging\Filters\LogLevelFilter;
use Psr\Log\LogLevel;

describe('LogLevelFilter', function () {
    it('filters by minimum log level', function () {
        $filter = new LogLevelFilter(LogLevel::WARNING);

        $emergencyEvent = new TestEvent(LogLevel::EMERGENCY);
        $warningEvent = new TestEvent(LogLevel::WARNING);
        $debugEvent = new TestEvent(LogLevel::DEBUG);

        expect($filter($emergencyEvent))->toBeTrue()
            ->and($filter($warningEvent))->toBeTrue()
            ->and($filter($debugEvent))->toBeFalse();
    });

    it('allows all events when minimum level is DEBUG', function () {
        $filter = new LogLevelFilter(LogLevel::DEBUG);

        $emergencyEvent = new TestEvent(LogLevel::EMERGENCY);
        $debugEvent = new TestEvent(LogLevel::DEBUG);

        expect($filter($emergencyEvent))->toBeTrue()
            ->and($filter($debugEvent))->toBeTrue();
    });

    it('blocks all but emergency when minimum level is EMERGENCY', function () {
        $filter = new LogLevelFilter(LogLevel::EMERGENCY);

        $emergencyEvent = new TestEvent(LogLevel::EMERGENCY);
        $errorEvent = new TestEvent(LogLevel::ERROR);

        expect($filter($emergencyEvent))->toBeTrue()
            ->and($filter($errorEvent))->toBeFalse();
    });
});

class TestEvent extends Event
{
    public function __construct(string $logLevel, mixed $data = null)
    {
        parent::__construct($data);
        $this->logLevel = $logLevel;
    }

    public function name(): string
    {
        return 'test_event';
    }
}