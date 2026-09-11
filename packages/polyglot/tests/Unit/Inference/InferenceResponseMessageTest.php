<?php declare(strict_types=1);

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCallId;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;

function orderedAssistantResponseMessage(): Message {
    $call = (new ToolCall(
        name: 'weather',
        id: new ToolCallId('call-weather'),
    ))->withArguments('{ "city" : "Warsaw" }');

    return (new Message(
        role: MessageRole::Assistant,
        parts: new ContentParts(
            ContentPart::reasoning('Need current conditions.'),
            ContentPart::text('I will check.'),
            ContentPart::toolCall($call),
            ContentPart::text('One moment.'),
        ),
    ))->withMetadata(ReplayEnvelope::MESSAGE_METADATA_KEY, [
        'version' => ReplayEnvelope::CURRENT_VERSION,
        'owner' => 'test-provider',
        'response' => ['id' => 'response-1'],
        'parts' => [
            ['signature' => 'thought-signature'],
            null,
            ['item_id' => 'tool-item'],
            null,
        ],
    ]);
}

it('stores the exact ordered assistant Message as its canonical value', function () {
    $message = orderedAssistantResponseMessage();

    $response = new InferenceResponse(message: $message, finishReason: 'tool_calls');

    expect($response->message())->toBe($message)
        ->and($response->message()->parts()->map(fn(ContentPart $part) => $part->type()))
        ->toBe(['reasoning', 'text', 'tool_call', 'text'])
        ->and($response->message()->content()->toString())->toBe("I will check.\nOne moment.")
        ->and($response->message()->reasoningContent())->toBe('Need current conditions.')
        ->and($response->message()->toolCalls()->first()?->argsAsJson())->toBe('{ "city" : "Warsaw" }');
});

it('round-trips only the canonical message', function () {
    $response = new InferenceResponse(message: orderedAssistantResponseMessage());
    $serialized = $response->toArray();

    $copy = InferenceResponse::fromArray($serialized);

    expect($copy->message()->id()->toString())->toBe($response->message()->id()->toString())
        ->and($copy->message()->parts()->toArray())->toBe($response->message()->parts()->toArray())
        ->and($copy->message()->metadata()->toArray())->toBe($response->message()->metadata()->toArray())
        ->and(ReplayEnvelope::fromMessage($copy->message(), 'test-provider')?->toArray())->toBe([
            'version' => ReplayEnvelope::CURRENT_VERSION,
            'owner' => 'test-provider',
            'response' => ['id' => 'response-1'],
            'parts' => [
                ['signature' => 'thought-signature'],
                null,
                ['item_id' => 'tool-item'],
                null,
            ],
        ])
        ->and(array_keys($serialized))->not->toContain('content', 'reasoningContent', 'toolCalls');
});

it('rejects flattened response payloads without a canonical message', function () {
    InferenceResponse::fromArray([
        'content' => 'Legacy answer',
        'reasoningContent' => 'Legacy thought',
        'toolCalls' => [],
    ]);
})->throws(InvalidArgumentException::class, 'Flattened content, reasoningContent, and toolCalls fields are not supported');

it('rejects flattened response fields even beside a canonical message', function () {
    InferenceResponse::fromArray([
        'message' => Message::asAssistant('Canonical answer')->toArray(),
        'content' => 'Conflicting legacy answer',
    ]);
})->throws(InvalidArgumentException::class, 'Flattened content, reasoningContent, and toolCalls fields are not supported');

it('copies response metadata while retaining the exact canonical Message', function () {
    $response = new InferenceResponse(message: orderedAssistantResponseMessage());

    $copy = $response->with(finishReason: 'stop', isPartial: true);

    expect($copy->message())->toBe($response->message())
        ->and($copy->id)->toBe($response->id)
        ->and($copy->createdAt)->toBe($response->createdAt)
        ->and($copy->updatedAt)->toBe($response->updatedAt)
        ->and($copy->finishReason()->value)->toBe('stop')
        ->and($copy->isPartial())->toBeTrue();
});

it('replaces the canonical message through the message mutator', function () {
    $response = new InferenceResponse(message: orderedAssistantResponseMessage());
    $replacement = Message::asAssistant('Replacement');

    $copy = $response->withMessage($replacement);

    expect($copy->message())->toBe($replacement)
        ->and($copy->message()->content()->toString())->toBe('Replacement');
});

it('rejects a non-assistant canonical message', function () {
    new InferenceResponse(message: Message::asUser('not an assistant response'));
})->throws(InvalidArgumentException::class, 'InferenceResponse message must have the assistant role.');
