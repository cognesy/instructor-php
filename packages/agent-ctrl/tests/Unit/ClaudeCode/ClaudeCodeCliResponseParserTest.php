<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\ClaudeCode\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\ErrorEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\MessageEvent;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\ResultEvent;
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
        ->and($response->messageText())->toBe('Claude');
});

it('stores raw stdout as message text for text output', function () {
    $result = new ExecResult('plain text output', '', 0, 0.1);

    $response = (new ResponseParser())->parse($result, OutputFormat::Text);

    expect($response->decoded()->isEmpty())->toBeTrue()
        ->and($response->messageText())->toBe('plain text output');
});

it('normalizes structured values in stream events without type errors', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"assistant","message":{"role":null,"content":[{"type":"text","text":123},{"type":"tool_use","id":456,"name":false,"input":"invalid"},{"type":"tool_result","tool_use_id":789,"content":[{"type":"text","text":"ok"}],"is_error":"true"}]}}',
        '{"type":"result","result":{"status":"ok"}}',
        '{"type":"error","error":{"message":"boom"}}',
    ]);
    $result = new ExecResult($stdout, '', 0, 0.1);

    $response = (new ResponseParser())->parse($result, OutputFormat::StreamJson);
    $events = $response->events()->all();

    expect($events[0])->toBeInstanceOf(MessageEvent::class)
        ->and($events[1])->toBeInstanceOf(ResultEvent::class)
        ->and($events[2])->toBeInstanceOf(ErrorEvent::class);

    /** @var MessageEvent $messageEvent */
    $messageEvent = $events[0];
    $text = $messageEvent->message->textContent();
    $toolUses = $messageEvent->message->toolUses();
    $toolResults = $messageEvent->message->toolResults();

    expect($messageEvent->message->role)->toBe('unknown')
        ->and($text)->toHaveCount(1)
        ->and($text[0]->text)->toBe('123')
        ->and($toolUses)->toHaveCount(1)
        ->and($toolUses[0]->id)->toBe('456')
        ->and($toolUses[0]->name)->toBe('false')
        ->and($toolUses[0]->input)->toBe([])
        ->and($toolResults)->toHaveCount(1)
        ->and($toolResults[0]->toolUseId)->toBe('789')
        ->and($toolResults[0]->content)->toBe('[{"type":"text","text":"ok"}]')
        ->and($toolResults[0]->isError)->toBeTrue();

    /** @var ResultEvent $resultEvent */
    $resultEvent = $events[1];
    expect($resultEvent->result)->toBe('{"status":"ok"}');

    /** @var ErrorEvent $errorEvent */
    $errorEvent = $events[2];
    expect($errorEvent->error)->toBe('{"message":"boom"}');
});
