<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Pi\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\AgentEndEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\AgentStartEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\MessageEndEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\MessageStartEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\MessageUpdateEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\SessionEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\ToolExecutionEndEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\TurnEndEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\TurnStartEvent;
use Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent\UnknownEvent;
use Cognesy\AgentCtrl\Pi\Domain\Enum\OutputMode;
use Cognesy\AgentCtrl\Pi\Domain\ValueObject\PiSessionId;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Utils\Json\JsonParsingException;

it('parses pi jsonl with session header and text deltas', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"session","version":3,"id":"df576fbe-dd53-40f1-bad5-43dedf088bfb","timestamp":"2026-03-10T17:53:48.547Z","cwd":"/Users/test"}',
        '{"type":"agent_start"}',
        '{"type":"turn_start"}',
        '{"type":"message_start","message":{"role":"user","content":[{"type":"text","text":"Say hello"}],"timestamp":1773165228555}}',
        '{"type":"message_end","message":{"role":"user","content":[{"type":"text","text":"Say hello"}],"timestamp":1773165228555}}',
        '{"type":"message_start","message":{"role":"assistant","content":[],"model":"claude-opus-4-6"}}',
        '{"type":"message_update","assistantMessageEvent":{"type":"text_delta","contentIndex":0,"delta":"Hello"},"message":{"role":"assistant","content":[{"type":"text","text":"Hello"}]}}',
        '{"type":"message_update","assistantMessageEvent":{"type":"text_delta","contentIndex":0,"delta":"!"},"message":{"role":"assistant","content":[{"type":"text","text":"Hello!"}]}}',
        '{"type":"message_end","message":{"role":"assistant","content":[{"type":"text","text":"Hello!"}],"usage":{"input":3,"output":5,"cacheRead":0,"cacheWrite":4625,"totalTokens":4633,"cost":{"input":0.000015,"output":0.000125,"cacheRead":0,"cacheWrite":0.028906,"total":0.029046}}}}',
        '{"type":"turn_end","message":{"role":"assistant","content":[{"type":"text","text":"Hello!"}]},"toolResults":[]}',
        '{"type":"agent_end","messages":[{"role":"user","content":[{"type":"text","text":"Say hello"}]},{"role":"assistant","content":[{"type":"text","text":"Hello!"}],"usage":{"input":3,"output":5,"cacheRead":0,"cacheWrite":4625,"totalTokens":4633,"cost":{"input":0.000015,"output":0.000125,"cacheRead":0,"cacheWrite":0.028906,"total":0.029046}}}]}',
    ]);
    $result = new ExecResult($stdout, '', 0, 0.5);

    $parser = new ResponseParser();
    $response = $parser->parse($result, OutputMode::Json);
    $events = $parser->parseEvents($response->decoded());

    expect($response->decoded()->count())->toBe(11)
        ->and($response->sessionId())->toBeInstanceOf(PiSessionId::class)
        ->and((string) ($response->sessionId() ?? ''))->toBe('df576fbe-dd53-40f1-bad5-43dedf088bfb')
        ->and($response->messageText())->toBe('Hello!')
        ->and($response->usage())->not->toBeNull()
        ->and($response->usage()?->input)->toBe(3)
        ->and($response->usage()?->output)->toBe(5)
        ->and($response->usage()?->cacheWrite)->toBe(4625)
        ->and($response->cost())->toBe(0.029046)
        ->and($response->isSuccess())->toBeTrue()
        ->and($events[0])->toBeInstanceOf(SessionEvent::class)
        ->and($events[1])->toBeInstanceOf(AgentStartEvent::class)
        ->and($events[2])->toBeInstanceOf(TurnStartEvent::class)
        ->and($events[3])->toBeInstanceOf(MessageStartEvent::class)
        ->and($events[6])->toBeInstanceOf(MessageUpdateEvent::class)
        ->and($events[10])->toBeInstanceOf(AgentEndEvent::class);
});

it('extracts tool calls from tool_execution_end events', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"session","version":3,"id":"sess-1","timestamp":"2026-01-01T00:00:00Z","cwd":"/tmp"}',
        '{"type":"agent_start"}',
        '{"type":"tool_execution_start","toolCallId":"call_1","toolName":"bash","args":{"command":"ls"}}',
        '{"type":"tool_execution_end","toolCallId":"call_1","toolName":"bash","result":"file1.txt\nfile2.txt","isError":false}',
        '{"type":"tool_execution_start","toolCallId":"call_2","toolName":"read","args":{"path":"file1.txt"}}',
        '{"type":"tool_execution_end","toolCallId":"call_2","toolName":"read","result":"content","isError":false}',
        '{"type":"message_end","message":{"role":"assistant","content":[{"type":"text","text":"Done"}],"usage":{"input":10,"output":20,"cacheRead":0,"cacheWrite":0,"totalTokens":30,"cost":{"total":0.001}}}}',
        '{"type":"agent_end","messages":[]}',
    ]);
    $result = new ExecResult($stdout, '', 0, 1.0);

    $parser = new ResponseParser();
    $events = $parser->parseEvents($parser->parse($result, OutputMode::Json)->decoded());

    $toolEvents = array_values(array_filter($events, fn($e) => $e instanceof ToolExecutionEndEvent));
    expect($toolEvents)->toHaveCount(2)
        ->and($toolEvents[0]->toolName)->toBe('bash')
        ->and($toolEvents[0]->toolCallId)->toBe('call_1')
        ->and($toolEvents[0]->resultAsString())->toBe("file1.txt\nfile2.txt")
        ->and($toolEvents[0]->isError)->toBeFalse()
        ->and($toolEvents[1]->toolName)->toBe('read')
        ->and($toolEvents[1]->toolCallId)->toBe('call_2');
});

it('throws on malformed pi jsonl line', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"session","version":3,"id":"sess","timestamp":"t","cwd":"/"}',
        'not-json',
    ]);
    $result = new ExecResult($stdout, '', 1, 0.1);

    expect(fn() => (new ResponseParser())->parse($result, OutputMode::Json))
        ->toThrow(JsonParsingException::class);
});

it('can disable fail-fast for malformed pi jsonl line', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"session","version":3,"id":"sess","timestamp":"t","cwd":"/"}',
        'not-json',
        '{"type":"message_update","assistantMessageEvent":{"type":"text_delta","delta":"ok"},"message":{}}',
    ]);
    $result = new ExecResult($stdout, '', 0, 0.1);

    $response = (new ResponseParser(false))->parse($result, OutputMode::Json);

    expect($response->decoded()->count())->toBe(2)
        ->and($response->messageText())->toBe('ok')
        ->and($response->parseFailures())->toBe(1)
        ->and($response->parseFailureSamples())->toHaveCount(1);
});

it('falls back to text mode for rpc output', function () {
    $result = new ExecResult('raw text output', '', 0, 0.1);

    $response = (new ResponseParser())->parse($result, OutputMode::Rpc);

    expect($response->messageText())->toBe('raw text output')
        ->and($response->decoded()->count())->toBe(0);
});

it('handles unknown event types gracefully', function () {
    $stdout = '{"type":"auto_compaction_start","reason":"threshold"}';
    $result = new ExecResult($stdout, '', 0, 0.1);

    $parser = new ResponseParser();
    $events = $parser->parseEvents($parser->parse($result, OutputMode::Json)->decoded());

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(UnknownEvent::class)
        ->and($events[0]->type())->toBe('auto_compaction_start');
});
