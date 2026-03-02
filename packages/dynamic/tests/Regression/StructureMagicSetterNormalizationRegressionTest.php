<?php declare(strict_types=1);

use Cognesy\Dynamic\Structure;
use Cognesy\Schema\SchemaBuilder;
use Cognesy\Schema\SchemaFactory;
use Symfony\Component\TypeInfo\Type;

it('rejects writes through magic setter to preserve record immutability', function () {
    $schema = SchemaBuilder::define('event')
        ->withProperty(
            'scheduled_at',
            SchemaFactory::default()->fromType(Type::object(\DateTimeImmutable::class), 'scheduled_at'),
        )
        ->schema();
    $structure = Structure::fromSchema($schema);

    expect(fn() => $structure->scheduled_at = new \DateTimeImmutable('2026-01-02T03:04:05+00:00'))
        ->toThrow(BadMethodCallException::class, 'Structure is immutable. Use set() and reassign returned instance.');
});

it('keeps set immutable and returns updated copy', function () {
    $schema = SchemaBuilder::define('event')
        ->string('name')
        ->schema();
    $structure = Structure::fromSchema($schema);

    $updated = $structure->set('name', 'Launch');

    expect($structure->toArray())->toBe([])
        ->and($updated->toArray())->toBe(['name' => 'Launch']);
});

it('rejects unknown fields assigned through magic setter', function () {
    $schema = SchemaBuilder::define('event')
        ->string('name')
        ->schema();
    $structure = Structure::fromSchema($schema);

    expect(fn() => $structure->unknown = 'x')
        ->toThrow(InvalidArgumentException::class, 'Property not found: unknown');
});

it('rejects unknown fields accessed via get', function () {
    $schema = SchemaBuilder::define('event')
        ->string('name')
        ->schema();
    $structure = Structure::fromSchema($schema);

    expect(fn() => $structure->get('unknown'))
        ->toThrow(InvalidArgumentException::class, 'Property not found: unknown');
});
