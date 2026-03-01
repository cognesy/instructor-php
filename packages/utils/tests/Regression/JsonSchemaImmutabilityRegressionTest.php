<?php declare(strict_types=1);

use Cognesy\Utils\JsonSchema\JsonSchema;

it('keeps fluent mutation methods immutable', function () {
    $base = JsonSchema::object(
        name: 'Base',
        properties: [JsonSchema::string(name: 'id')],
        requiredProperties: ['id'],
    );

    $updated = $base
        ->withName('Updated')
        ->withDescription('Updated description')
        ->withAdditionalProperties(true);

    expect($updated)->not->toBe($base);
    expect($base->name())->toBe('Base');
    expect($base->description())->toBe('');
    expect($base->hasAdditionalProperties())->toBeFalse();
    expect($updated->name())->toBe('Updated');
    expect($updated->description())->toBe('Updated description');
    expect($updated->hasAdditionalProperties())->toBeTrue();
});

it('does not mutate source schema when renaming reused property node', function () {
    $property = JsonSchema::string(name: 'original');

    $schema = JsonSchema::object(properties: [$property]);

    expect($property->name())->toBe('original');
    expect(array_keys($schema->properties()))->toBe(['original']);
});

