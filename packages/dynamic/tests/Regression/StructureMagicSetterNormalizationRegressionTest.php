<?php declare(strict_types=1);

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\StructureBuilder;

it('rejects writes through magic setter to preserve record immutability', function () {
    $structure = StructureBuilder::define('event')
        ->withField(Field::datetime('scheduled_at'))
        ->build();

    expect(fn() => $structure->scheduled_at = new \DateTimeImmutable('2026-01-02T03:04:05+00:00'))
        ->toThrow(BadMethodCallException::class, 'Structure is immutable. Use set() and reassign returned instance.');
});

it('keeps set immutable and returns updated copy', function () {
    $structure = StructureBuilder::define('event')
        ->string('name')
        ->build();

    $updated = $structure->set('name', 'Launch');

    expect($structure->toArray())->toBe([])
        ->and($updated->toArray())->toBe(['name' => 'Launch']);
});

it('rejects unknown fields assigned through magic setter', function () {
    $structure = StructureBuilder::define('event')
        ->string('name')
        ->build();

    expect(fn() => $structure->unknown = 'x')
        ->toThrow(InvalidArgumentException::class, 'Field not found: unknown');
});

it('rejects unknown fields accessed via get', function () {
    $structure = StructureBuilder::define('event')
        ->string('name')
        ->build();

    expect(fn() => $structure->get('unknown'))
        ->toThrow(InvalidArgumentException::class, 'Field not found: unknown');
});
