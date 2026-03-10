<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Messages\ToolCallId;

it('preserves tool call id through delta construction', function () {
    $toolId = ToolCallId::fromString('call_123');

    $partial = new PartialInferenceDelta(
        toolId: $toolId,
        toolName: 'search',
        toolArgs: '{"q":"test"}',
    );

    expect($partial->toolId)->toBeInstanceOf(ToolCallId::class)
        ->and((string) ($partial->toolId ?? ''))->toBe('call_123')
        ->and($partial->toolName)->toBe('search')
        ->and($partial->toolArgs)->toBe('{"q":"test"}');
});
