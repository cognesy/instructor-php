<?php

declare(strict_types=1);

use Cognesy\Tell\Data\TellCommandDescriptor;
use Symfony\Component\Console\Command\Command;

it('keeps command descriptors framework neutral while supporting Symfony adapters', function (): void {
    $descriptor = new TellCommandDescriptor(
        name: 'fixture:inspect',
        factory: static fn (): object => new Command('fixture:inspect'),
        description: 'Inspect a fixture',
        aliases: ['fixture:i'],
    );

    $first = $descriptor->create();
    $second = $descriptor->create();

    expect($first)->toBeInstanceOf(Command::class)
        ->and($first)->not->toBe($second)
        ->and($first->getName())->toBe('fixture:inspect')
        ->and($descriptor->aliases)->toBe(['fixture:i']);
});

it('rejects invalid command identities before shell assembly', function (string $name): void {
    new TellCommandDescriptor($name, static fn (): object => new stdClass());
})->with(['Uppercase', 'contains space', ':prefix'])->throws(InvalidArgumentException::class);
