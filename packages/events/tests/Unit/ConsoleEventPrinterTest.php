<?php

use Cognesy\Events\Contracts\CanFormatConsoleEvent;
use Cognesy\Events\Data\ConsoleEventLine;
use Cognesy\Events\Enums\ConsoleColor;
use Cognesy\Events\Support\ConsoleEventPrinter;

test('console event printer prints formatted line via wiretap', function () {
    $printer = new ConsoleEventPrinter(useColors: false, showTimestamps: false);
    $formatter = new class implements CanFormatConsoleEvent {
        public function format(object $event): ?ConsoleEventLine {
            return new ConsoleEventLine('TEST', 'hello world', ConsoleColor::Green, '[ctx]');
        }
    };

    ob_start();
    $wiretap = $printer->wiretap($formatter);
    $wiretap(new stdClass());
    $output = ob_get_clean();

    expect($output)->toContain('[ctx] [TEST] hello world');
});


test('console event printer does not print for null line', function () {
    $printer = new ConsoleEventPrinter(useColors: false, showTimestamps: false);

    ob_start();
    $printer->printIfAny(null);
    $output = ob_get_clean();

    expect($output)->toBe('');
});
