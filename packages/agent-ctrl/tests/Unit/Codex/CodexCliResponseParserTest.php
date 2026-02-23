<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\OpenAICodex\Application\Parser\ResponseParser;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\ThreadStartedEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent\UnknownEvent;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\OpenAICodex\Domain\ValueObject\CodexThreadId;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Utils\Json\JsonParsingException;

it('parses codex jsonl and normalizes ids', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"thread.started","thread_id":"thread_abc"}',
        '{"type":"item.completed","item":{"id":"item_1","type":"agent_message","status":"completed","text":"hello"}}',
        '{"type":"turn.completed","usage":{"input_tokens":10,"cached_input_tokens":2,"output_tokens":3}}',
    ]);
    $result = new ExecResult($stdout, '', 0, 0.1);

    $response = (new ResponseParser())->parse($result, OutputFormat::Json);
    $events = (new ResponseParser())->parseEvents($response->decoded());

    expect($response->decoded()->count())->toBe(3)
        ->and($response->threadId())->toBeInstanceOf(CodexThreadId::class)
        ->and((string) ($response->threadId() ?? ''))->toBe('thread_abc')
        ->and($response->messageText())->toBe('hello')
        ->and($response->usage()?->inputTokens)->toBe(10)
        ->and($response->usage()?->cachedInputTokens)->toBe(2)
        ->and($response->usage()?->outputTokens)->toBe(3)
        ->and($events)->toHaveCount(3)
        ->and($events[0])->toBeInstanceOf(ThreadStartedEvent::class);
});

it('handles unknown event types without crashing', function () {
    $stdout = '{"type":"something.unexpected","value":123}';
    $result = new ExecResult($stdout, '', 0, 0.1);

    $response = (new ResponseParser())->parse($result, OutputFormat::Json);
    $events = (new ResponseParser())->parseEvents($response->decoded());

    expect($response->decoded()->count())->toBe(1)
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(UnknownEvent::class);
});

it('throws on malformed jsonl line', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"thread.started","thread_id":"thread_abc"}',
        'not-json-line',
    ]);
    $result = new ExecResult($stdout, '', 0, 0.1);

    expect(fn() => (new ResponseParser())->parse($result, OutputFormat::Json))
        ->toThrow(JsonParsingException::class);
});

it('can disable fail-fast for malformed jsonl line', function () {
    $stdout = implode(PHP_EOL, [
        '{"type":"thread.started","thread_id":"thread_abc"}',
        'not-json-line',
        '{"type":"turn.completed","usage":{"input_tokens":10,"cached_input_tokens":2,"output_tokens":3}}',
    ]);
    $result = new ExecResult($stdout, '', 0, 0.1);

    $response = (new ResponseParser(false))->parse($result, OutputFormat::Json);

    expect($response->decoded()->count())->toBe(2)
        ->and((string) ($response->threadId() ?? ''))->toBe('thread_abc')
        ->and($response->usage()?->inputTokens)->toBe(10)
        ->and($response->parseFailures())->toBe(1)
        ->and($response->parseFailureSamples())->toHaveCount(1);
});

it('stores raw stdout as message text for text output', function () {
    $result = new ExecResult('plain text output', '', 0, 0.1);

    $response = (new ResponseParser())->parse($result, OutputFormat::Text);

    expect($response->decoded()->isEmpty())->toBeTrue()
        ->and($response->messageText())->toBe('plain text output');
});
