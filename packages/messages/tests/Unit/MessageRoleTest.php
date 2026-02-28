<?php declare(strict_types=1);

use Cognesy\Messages\Enums\MessageRole;

it('throws for unknown role strings instead of defaulting to user', function () {
    expect(fn() => MessageRole::fromString('function'))
        ->toThrow(InvalidArgumentException::class, 'Invalid message role: function');
});
