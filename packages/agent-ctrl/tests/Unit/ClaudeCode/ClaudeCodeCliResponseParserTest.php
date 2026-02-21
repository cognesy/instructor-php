<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\UnknownEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\Sandbox\Data\ExecResult;

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

it('returns empty collection on invalid json', function () {
    $result = new ExecResult('not-json', '', 1, 0.1);

    $response = (new ResponseParser())->parse($result, OutputFormat::Json);

    expect($response->decoded()->isEmpty())->toBeTrue();
    expect($response->events()->isEmpty())->toBeTrue();
});
