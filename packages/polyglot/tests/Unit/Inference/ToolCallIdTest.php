<?php declare(strict_types=1);

use Cognesy\Messages\ToolCallId;

it('creates tool call external id from string', function () {
    $id = ToolCallId::fromString('call_abc123');

    expect($id->toString())->toBe('call_abc123')
        ->and((string) $id)->toBe('call_abc123');
});

it('allows empty tool call id', function () {
    $id = new ToolCallId('');

    expect($id->isEmpty())->toBeTrue()
        ->and($id->toNullableString())->toBeNull();
});

it('creates null tool call id via factory', function () {
    $id = ToolCallId::null();

    expect($id->isEmpty())->toBeTrue()
        ->and($id->toString())->toBe('');
});

it('creates empty tool call id via explicit factory', function () {
    $id = ToolCallId::empty();

    expect($id->isEmpty())->toBeTrue()
        ->and($id->isPresent())->toBeFalse()
        ->and($id->toString())->toBe('');
});
