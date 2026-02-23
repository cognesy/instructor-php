<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\OpenCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\UnknownEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeMessageId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeSessionId;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Utils\Json\JsonParsingException;

it('parses opencode jsonl and aggregates usage', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"step_start","timestamp":1,"sessionID":"sess_1","part":{"messageID":"msg_1","id":"part_1","snapshot":"snap1"}}',
        '{"type":"text","timestamp":2,"sessionID":"sess_1","part":{"messageID":"msg_1","id":"part_2","text":"Hello ","time":{"start":2,"end":3}}}',
        '{"type":"text","timestamp":3,"sessionID":"sess_1","part":{"messageID":"msg_1","id":"part_3","text":"OpenCode","time":{"start":3,"end":4}}}',
        '{"type":"step_finish","timestamp":4,"sessionID":"sess_1","part":{"messageID":"msg_1","id":"part_4","reason":"tool-calls","snapshot":"snap2","cost":0.1,"tokens":{"input":10,"output":2,"reasoning":1,"cache":{"read":1,"write":0}}}}',
        '{"type":"step_finish","timestamp":5,"sessionID":"sess_1","part":{"messageID":"msg_2","id":"part_5","reason":"stop","snapshot":"snap3","cost":0.2,"tokens":{"input":5,"output":1,"reasoning":0,"cache":{"read":2,"write":1}}}}',
        '{"type":"unexpected.kind","timestamp":6,"sessionID":"sess_1"}',
    ]);
    $result = new ExecResult($stdout, '', 0, 0.1);

    $parser = new ResponseParser();
    $response = $parser->parse($result, OutputFormat::Json);
    $events = $parser->parseEvents($response->decoded());

    expect($response->decoded()->count())->toBe(6)
        ->and($response->sessionId())->toBeInstanceOf(OpenCodeSessionId::class)
        ->and((string) ($response->sessionId() ?? ''))->toBe('sess_1')
        ->and($response->messageId())->toBeInstanceOf(OpenCodeMessageId::class)
        ->and((string) ($response->messageId() ?? ''))->toBe('msg_2')
        ->and($response->messageText())->toBe('Hello OpenCode')
        ->and($response->usage()?->input)->toBe(15)
        ->and($response->usage()?->output)->toBe(3)
        ->and($response->usage()?->reasoning)->toBe(1)
        ->and($response->usage()?->cacheRead)->toBe(3)
        ->and($response->usage()?->cacheWrite)->toBe(1)
        ->and(round($response->cost() ?? 0.0, 2))->toBe(0.3)
        ->and($events)->toHaveCount(6)
        ->and($events[5])->toBeInstanceOf(UnknownEvent::class);
});

it('throws on malformed jsonl line', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"step_start","timestamp":1,"sessionID":"sess_1","part":{"messageID":"msg_1","id":"part_1","snapshot":"snap1"}}',
        'not-json',
    ]);
    $result = new ExecResult($stdout, '', 1, 0.1);

    expect(fn() => (new ResponseParser())->parse($result, OutputFormat::Json))
        ->toThrow(JsonParsingException::class);
});

it('throws on invalid json output', function () {
    $result = new ExecResult('not-json', '', 1, 0.1);

    expect(fn() => (new ResponseParser())->parse($result, OutputFormat::Json))
        ->toThrow(JsonParsingException::class);
});

it('can disable fail-fast for malformed jsonl line', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"text","timestamp":2,"sessionID":"sess_1","part":{"messageID":"msg_1","id":"part_2","text":"Hello ","time":{"start":2,"end":3}}}',
        'not-json',
        '{"type":"text","timestamp":3,"sessionID":"sess_1","part":{"messageID":"msg_1","id":"part_3","text":"OpenCode","time":{"start":3,"end":4}}}',
    ]);
    $result = new ExecResult($stdout, '', 0, 0.1);

    $response = (new ResponseParser(false))->parse($result, OutputFormat::Json);

    expect($response->decoded()->count())->toBe(2)
        ->and($response->messageText())->toBe('Hello OpenCode')
        ->and($response->parseFailures())->toBe(1)
        ->and($response->parseFailureSamples())->toHaveCount(1);
});
