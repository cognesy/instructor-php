<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Streaming\EventStreamReader;

it('parses multi-line SSE data as a single event payload', function () {
    $reader = new EventStreamReader(
        events: new EventDispatcher(),
        parser: static fn(string $line): string|bool => trim(substr($line, 5)),
    );

    $stream = function () {
        yield "event: message\n";
        yield "data: {\"choices\":[{\"delta\":{\"content\":\"Hello\"},\n";
        yield "data: \"finish_reason\":null}]}\n";
        yield "\n";
    };

    $result = iterator_to_array($reader->eventsFrom($stream()));

    expect($result)->toHaveCount(1)
        ->and($result[0])->toBe("{\"choices\":[{\"delta\":{\"content\":\"Hello\"},\n\"finish_reason\":null}]}");
});
