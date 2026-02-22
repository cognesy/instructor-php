<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCallId;

it('round-trips tool call id through partial response array boundary', function () {
    $partial = new PartialInferenceResponse(
        toolId: 'call_123',
        toolName: 'search',
        toolArgs: '{"q":"test"}',
    );

    $serialized = $partial->toArray();
    $restored = PartialInferenceResponse::fromArray($serialized);

    expect($serialized['tool_id'])->toBe('call_123')
        ->and((string) ($restored->toolId() ?? ''))->toBe('call_123')
        ->and($restored->toolId())->toBeInstanceOf(ToolCallId::class)
        ->and((string) ($restored->toolId() ?? ''))->toBe('call_123');
});
