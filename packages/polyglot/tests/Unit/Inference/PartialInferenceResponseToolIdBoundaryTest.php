<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Messages\ToolCallId;

it('preserves tool call id through partial response construction', function () {
    $partial = new PartialInferenceResponse(
        toolId: 'call_123',
        toolName: 'search',
        toolArgs: '{"q":"test"}',
    );

    expect($partial->toolId)->toBe('call_123')
        ->and($partial->toolId())->toBeInstanceOf(ToolCallId::class)
        ->and((string) ($partial->toolId() ?? ''))->toBe('call_123')
        ->and($partial->toolName())->toBe('search')
        ->and($partial->toolArgs())->toBe('{"q":"test"}');
});
