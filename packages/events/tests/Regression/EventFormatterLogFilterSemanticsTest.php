<?php declare(strict_types=1);

use Cognesy\Events\Utils\EventFormatter;
use Psr\Log\LogLevel;

it('applies threshold filtering semantics for debug info and error levels', function (
    string $threshold,
    string $eventLevel,
    bool $expected,
) {
    expect(EventFormatter::logFilter($threshold, $eventLevel))->toBe($expected);
})->with([
    'debug threshold keeps debug' => [LogLevel::DEBUG, LogLevel::DEBUG, true],
    'info threshold drops debug' => [LogLevel::INFO, LogLevel::DEBUG, false],
    'info threshold keeps error' => [LogLevel::INFO, LogLevel::ERROR, true],
    'error threshold drops info' => [LogLevel::ERROR, LogLevel::INFO, false],
    'error threshold keeps error' => [LogLevel::ERROR, LogLevel::ERROR, true],
]);
