<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\ToolCallId;

it('creates tool call external id from string', function () {
    $id = ToolCallId::fromString('call_abc123');

    expect($id->toString())->toBe('call_abc123')
        ->and((string) $id)->toBe('call_abc123');
});

it('rejects empty tool call id', function () {
    new ToolCallId('');
})->throws(\InvalidArgumentException::class);
