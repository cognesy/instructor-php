<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Common\Execution\JsonLinesBuffer;

it('returns complete lines from a full chunk', function () {
    $buffer = new JsonLinesBuffer();

    expect($buffer->consume("{\"a\":1}\n{\"b\":2}\n"))->toBe(['{"a":1}', '{"b":2}'])
        ->and($buffer->flush())->toBe([]);
});

it('preserves partial line between chunks', function () {
    $buffer = new JsonLinesBuffer();

    expect($buffer->consume("{\"type\":\"message\",\"text\":\"hel"))->toBe([])
        ->and($buffer->consume("lo\"}\n{\"type\":\"result\"}\n"))->toBe([
            '{"type":"message","text":"hello"}',
            '{"type":"result"}',
        ]);
});

it('flushes trailing line without newline', function () {
    $buffer = new JsonLinesBuffer();

    expect($buffer->consume("{\"type\":\"message\"}"))->toBe([])
        ->and($buffer->flush())->toBe(['{"type":"message"}'])
        ->and($buffer->flush())->toBe([]);
});

it('normalizes mixed newline separators', function () {
    $buffer = new JsonLinesBuffer();

    expect($buffer->consume("{\"a\":1}\r\n{\"b\":2}\r{\"c\":3}\n"))->toBe([
        '{"a":1}',
        '{"b":2}',
        '{"c":3}',
    ]);
});
