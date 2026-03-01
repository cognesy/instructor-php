<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Streaming\EventStreamReader;

it('parses line-delimited data events without blank separators in parser mode', function () {
    $reader = new EventStreamReader(
        events: new EventDispatcher(),
        parser: static fn(string $line): string|bool => trim(substr($line, 5)),
    );

    $stream = function () {
        yield "data: {\"delta\":\"A\"}\n";
        yield "data: {\"delta\":\"B\"}\n";
    };

    $result = iterator_to_array($reader->eventsFrom($stream()));

    expect($result)->toBe([
        '{"delta":"A"}',
        '{"delta":"B"}',
    ]);
});

it('stops line-delimited parser stream on done marker without blank separators', function () {
    $reader = new EventStreamReader(
        events: new EventDispatcher(),
        parser: static function (string $line): string|bool {
            $payload = trim(substr($line, 5));
            return $payload === '[DONE]' ? false : $payload;
        },
    );

    $stream = function () {
        yield "data: {\"delta\":\"A\"}\n";
        yield "data: [DONE]\n";
        yield "data: {\"delta\":\"B\"}\n";
    };

    $result = iterator_to_array($reader->eventsFrom($stream()));

    expect($result)->toBe([
        '{"delta":"A"}',
    ]);
});
