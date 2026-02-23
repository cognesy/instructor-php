<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\UnknownEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Utils\Json\JsonParsingException;

it('parses json output into decoded objects', function () {
    $stdout = '[{"text":"hi","cost":1},{"text":"ok","cost":2}]';
    $result = new ExecResult($stdout, '', 0, 0.1);

    $response = (new ResponseParser())->parse($result, OutputFormat::Json);

    expect($response->decoded()->count())->toBe(2);
    $first = $response->decoded()->all()[0]->data();
    expect($first['text'])->toBe('hi');
    expect($response->events()->count())->toBe(2);
    expect($response->events()->all()[0])->toBeInstanceOf(UnknownEvent::class);
});

it('parses stream-json output line by line', function () {
    $stdout = '{"event":"partial","text":"hi"}' . PHP_EOL . '{"event":"final","text":"done"}';
    $result = new ExecResult($stdout, '', 0, 0.1);

    $response = (new ResponseParser())->parse($result, OutputFormat::StreamJson);

    expect($response->decoded()->count())->toBe(2);
    $last = $response->decoded()->all()[1]->data();
    expect($last['event'])->toBe('final');
    expect($response->events()->count())->toBe(2);
    expect($response->events()->all()[1])->toBeInstanceOf(UnknownEvent::class);
});

it('throws on invalid json', function () {
    $result = new ExecResult('not-json', '', 1, 0.1);

    expect(fn() => (new ResponseParser())->parse($result, OutputFormat::Json))
        ->toThrow(JsonParsingException::class);
});

it('can disable fail-fast for invalid json', function () {
    $result = new ExecResult('not-json', '', 1, 0.1);

    $response = (new ResponseParser(false))->parse($result, OutputFormat::Json);

    expect($response->decoded()->isEmpty())->toBeTrue()
        ->and($response->events()->isEmpty())->toBeTrue()
        ->and($response->parseFailures())->toBe(1)
        ->and($response->parseFailureSamples())->toHaveCount(1);
});

it('accumulates message text from stream-json message events', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"assistant","message":{"role":"assistant","content":[{"type":"text","text":"Hello "}]}}',
        '{"type":"assistant","message":{"role":"assistant","content":[{"type":"text","text":"Claude"}]}}',
    ]);
    $result = new ExecResult($stdout, '', 0, 0.1);

    $response = (new ResponseParser())->parse($result, OutputFormat::StreamJson);

    expect($response->decoded()->count())->toBe(2)
        ->and($response->messageText())->toBe('Hello Claude');
});

it('stores raw stdout as message text for text output', function () {
    $result = new ExecResult('plain text output', '', 0, 0.1);

    $response = (new ResponseParser())->parse($result, OutputFormat::Text);

    expect($response->decoded()->isEmpty())->toBeTrue()
        ->and($response->messageText())->toBe('plain text output');
});
