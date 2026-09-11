<?php declare(strict_types=1);

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Message;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolResult;

it('keeps typed tool payloads stable and serializes them only at the boundary', function () {
    $rawArguments = '{ "b": 2, "a": 1 }';
    $toolCall = ToolCall::fromArray([
        'id' => 'call_1',
        'name' => 'lookup',
        'arguments' => $rawArguments,
    ]);
    $toolResult = ToolResult::success('done', 'call_1', 'lookup');

    $callPart = ContentPart::toolCall($toolCall);
    $resultPart = ContentPart::toolResult($toolResult);

    expect($callPart->toToolCall())->toBe($toolCall)
        ->and($callPart->toToolCall())->toBe($toolCall)
        ->and($callPart->get('tool_call'))->toBe($toolCall->toArray())
        ->and($callPart->toArray()['tool_call'])->toBe($toolCall->toArray())
        ->and($resultPart->toToolResult())->toBe($toolResult)
        ->and($resultPart->toToolResult())->toBe($toolResult)
        ->and($resultPart->get('tool_result'))->toBe($toolResult->toArray());

    $hydratedCall = ContentPart::fromArray($callPart->toArray());
    $hydratedResult = ContentPart::fromArray($resultPart->toArray());
    $normalizedCall = $hydratedCall->toToolCall();
    $normalizedResult = $hydratedResult->toToolResult();

    expect($normalizedCall)->not->toBeNull()
        ->and($hydratedCall->toToolCall())->toBe($normalizedCall)
        ->and($normalizedCall?->argsAsJson())->toBe($rawArguments)
        ->and($normalizedResult)->not->toBeNull()
        ->and($hydratedResult->toToolResult())->toBe($normalizedResult);
});

it('precomputes stable projections for each immutable message', function () {
    $toolCall = (new ToolCall('lookup', id: 'call_1'))->withArguments('{ "q": "x" }');
    $toolResult = ToolResult::success('done', 'call_1', 'lookup');
    $message = new Message(
        role: 'assistant',
        metadata: ['trace' => 't1'],
        parts: new ContentParts(
            ContentPart::reasoning('Think.'),
            ContentPart::text('Before.'),
            ContentPart::toolCall($toolCall),
            ContentPart::toolResult($toolResult),
            ContentPart::text('After.'),
        ),
    );

    expect($message->content())->toBe($message->content())
        ->and($message->contentParts())->toBe($message->contentParts())
        ->and($message->toolCalls())->toBe($message->toolCalls())
        ->and($message->toolCalls()->first())->toBe($toolCall)
        ->and($message->toolResult())->toBe($toolResult)
        ->and($message->reasoningContent())->toBe('Think.')
        ->and($message->content()->toString())->toBe("Before.\nAfter.")
        ->and($message->isEmpty())->toBeFalse()
        ->and($message->isComposite())->toBeTrue();
});
