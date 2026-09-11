<?php declare(strict_types=1);

use Cognesy\Messages\ToolCallId;
use Cognesy\Polyglot\Inference\Data\AssistantMessageChunks;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

it('preserves tool call id through delta construction', function () {
    $toolId = ToolCallId::fromString('call_123');

    $partial = new PartialInferenceDelta(
        messageChunks: AssistantMessageChunks::empty()->withToolCallDelta(
            'provider:tool:0',
            $toolId,
            'search',
            '{"q":"test"}',
        ),
    );
    $toolChunk = $partial->messageChunks->all()[0];

    expect($toolChunk->toolCallId)->toBeInstanceOf(ToolCallId::class)
        ->and((string) ($toolChunk->toolCallId ?? ''))->toBe('call_123')
        ->and($toolChunk->toolCallName)->toBe('search')
        ->and($toolChunk->toolCallArguments)->toBe('{"q":"test"}');
});
