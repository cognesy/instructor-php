<?php declare(strict_types=1);

use Cognesy\Events\Event;
use Psr\Log\LogLevel;

class LogFilteringBehaviorEvent extends Event
{
    public string $logLevel = LogLevel::WARNING;
}

// Guards regression coverage from instructor-icd2 (log filtering behavior at Event API level).
it('does not print when event level is below threshold severity', function () {
    $event = new LogFilteringBehaviorEvent(['message' => 'warning']);

    ob_start();
    $event->print(quote: false, threshold: LogLevel::ERROR);
    $output = ob_get_clean();

    expect($output)->toBe('');
});

it('prints when event level meets threshold severity', function () {
    $event = new LogFilteringBehaviorEvent(['message' => 'warning']);

    ob_start();
    $event->print(quote: false, threshold: LogLevel::INFO);
    $output = ob_get_clean();

    expect($output)->not->toBe('');
    expect($output)->toContain('LogFilteringBehaviorEvent');
});

