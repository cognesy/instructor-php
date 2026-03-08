<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\OpenCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\ErrorEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\StepFinishEvent;
use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\ToolUseEvent;
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

it('normalizes mixed scalar and structured stream payloads', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"step_start","timestamp":"1","sessionID":"sess_mixed","part":{"messageID":456,"id":789,"snapshot":["snap"]}}',
        '{"type":"text","timestamp":"2","sessionID":"sess_mixed","part":{"messageID":456,"id":790,"text":123,"time":{"start":"2","end":"3"}}}',
        '{"type":"tool_use","timestamp":"3","sessionID":"sess_mixed","part":{"messageID":456,"id":791,"callID":1001,"tool":false,"state":{"status":true,"input":"invalid","output":[{"ok":1}],"title":["t"],"time":{"start":"3","end":"4"}}}}',
        '{"type":"error","timestamp":"4","sessionID":"sess_mixed","part":{"error":{"message":{"nested":"bad"},"code":5}}}',
        '{"type":"step_finish","timestamp":"5","sessionID":"sess_mixed","part":{"messageID":456,"id":792,"reason":null,"snapshot":9,"cost":"0.7","tokens":{"input":"10","output":"2","reasoning":"1","cache":{"read":"3","write":false}}}}',
    ]);
    $result = new ExecResult($stdout, '', 0, 0.1);

    $parser = new ResponseParser();
    $response = $parser->parse($result, OutputFormat::Json);
    $events = $parser->parseEvents($response->decoded());

    expect($response->messageText())->toBe('123')
        ->and((string) ($response->sessionId() ?? ''))->toBe('sess_mixed')
        ->and((string) ($response->messageId() ?? ''))->toBe('456')
        ->and($response->usage()?->input)->toBe(10)
        ->and($response->usage()?->output)->toBe(2)
        ->and($response->usage()?->reasoning)->toBe(1)
        ->and($response->usage()?->cacheRead)->toBe(3)
        ->and($response->usage()?->cacheWrite)->toBe(0)
        ->and($response->cost())->toBe(0.7)
        ->and($events)->toHaveCount(5)
        ->and($events[2])->toBeInstanceOf(ToolUseEvent::class)
        ->and($events[3])->toBeInstanceOf(ErrorEvent::class)
        ->and($events[4])->toBeInstanceOf(StepFinishEvent::class);

    /** @var ToolUseEvent $toolUse */
    $toolUse = $events[2];
    expect($toolUse->tool)->toBe('false')
        ->and($toolUse->status)->toBe('true')
        ->and($toolUse->input)->toBe([])
        ->and($toolUse->output)->toBe('[{"ok":1}]');

    /** @var ErrorEvent $error */
    $error = $events[3];
    expect($error->message)->toBe('{"nested":"bad"}')
        ->and($error->code)->toBe('5');
});
